<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OC\Files\Cache\Cache;
use OC\Files\Cache\CacheEntry;
use OC\Files\Node\Node;
use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCA\GroupFolders\ResponseDefinitions;
use OCP\AutoloadNotAllowedException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type GroupFoldersGroup from ResponseDefinitions
 * @psalm-import-type GroupFoldersCircle from ResponseDefinitions
 * @psalm-import-type GroupFoldersUser from ResponseDefinitions
 * @psalm-import-type GroupFoldersAclManage from ResponseDefinitions
 * @psalm-import-type GroupFoldersApplicable from ResponseDefinitions
 * @psalm-type InternalFolder = array{
 *   folder_id: int,
 *   mount_point: string,
 *   permissions: int,
 *   quota: int,
 *   acl: bool,
 *   rootCacheEntry: ?CacheEntry,
 * }
 * @psalm-type InternalFolderOut = array{
 *   id: int,
 *   mount_point: string,
 *   groups: array<string, GroupFoldersApplicable>,
 *   quota: int,
 *   size: int,
 *   acl: bool,
 *   manage: list<GroupFoldersAclManage>,
 * }
 * @psalm-type InternalFolderMapping = array{
 *   folder_id: int,
 *   mapping_type: 'user'|'group',
 *   mapping_id: string,
 * }
 */
class FolderManager {
	public const SPACE_DEFAULT = -4;

	public function __construct(
		private IDBConnection $connection,
		private IGroupManager $groupManager,
		private IMimeTypeLoader $mimeTypeLoader,
		private LoggerInterface $logger,
		private IEventDispatcher $eventDispatcher,
		private IConfig $config,
		private IUserMappingManager $userMappingManager,
		private readonly FolderStorageManager $folderStorageManager,
	) {
	}

	/**
	 * @return array<int, InternalFolderOut>
	 * @throws Exception
	 */
	public function getAllFolders(): array {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'mount_point', 'quota', 'acl')
			->from('group_folders', 'f');

		$rows = $query->executeQuery()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];
			$folderMap[$id] = [
				'id' => $id,
				'mount_point' => $row['mount_point'],
				'groups' => $applicableMap[$id] ?? [],
				'quota' => $this->getRealQuota((int)$row['quota']),
				'size' => 0,
				'acl' => (bool)$row['acl']
			];
		}

		return $folderMap;
	}

	/**
	 * @throws Exception
	 */
	private function getGroupFolderRootId(int $rootStorageId): int {
		$query = $this->connection->getQueryBuilder();

		$query->select('fileid')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createNamedParameter($rootStorageId)))
			->andWhere($query->expr()->eq('path_hash', $query->createNamedParameter(md5('__groupfolders'))));

		return (int)$query->executeQuery()->fetchOne();
	}

	private function joinQueryWithFileCache(IQueryBuilder $query, int $rootStorageId): void {
		$conditions = [
			$query->expr()->eq('c.fileid', 'f.root_id'),
		];
		if ($this->connection->getShardDefinition('filecache')) {
			$conditions[] = $query->expr()->eq('c.storage', $query->createNamedParameter($rootStorageId));
		}

		$query->leftJoin('f', 'filecache', 'c', $query->expr()->andX(...$conditions));
	}

	/**
	 * @return array<int, InternalFolderOut>
	 * @throws Exception
	 */
	public function getAllFoldersWithSize(int $rootStorageId): array {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'mount_point', 'quota', 'c.size', 'acl')
			->from('group_folders', 'f');
		$this->joinQueryWithFileCache($query, $rootStorageId);

		$rows = $query->executeQuery()->fetchAll();

		$folderMappings = $this->getAllFolderMappings();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];
			$mappings = $folderMappings[$id] ?? [];
			$folderMap[$id] = [
				'id' => $id,
				'mount_point' => $row['mount_point'],
				'groups' => $applicableMap[$id] ?? [],
				'quota' => $this->getRealQuota((int)$row['quota']),
				'size' => $row['size'] ? (int)$row['size'] : 0,
				'acl' => (bool)$row['acl'],
				'manage' => $this->getManageAcl($mappings)
			];
		}

		return $folderMap;
	}

	/**
	 * @return array<int, InternalFolderOut>
	 * @throws Exception
	 */
	public function getAllFoldersForUserWithSize(int $rootStorageId, IUser $user): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('f.folder_id', 'mount_point', 'quota', 'c.size', 'acl')
			->from('group_folders', 'f')
			->innerJoin(
				'f',
				'group_folders_groups',
				'a',
				$query->expr()->eq('f.folder_id', 'a.folder_id')
			)
			->where($query->expr()->in('a.group_id', $query->createNamedParameter($groups, IQueryBuilder::PARAM_STR_ARRAY)));
		$this->joinQueryWithFileCache($query, $rootStorageId);

		$rows = $query->executeQuery()->fetchAll();

		$folderMappings = $this->getAllFolderMappings();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];
			$mappings = $folderMappings[$id] ?? [];
			$folderMap[$id] = [
				'id' => $id,
				'mount_point' => $row['mount_point'],
				'groups' => $applicableMap[$id] ?? [],
				'quota' => $this->getRealQuota((int)$row['quota']),
				'size' => $row['size'] ? (int)$row['size'] : 0,
				'acl' => (bool)$row['acl'],
				'manage' => $this->getManageAcl($mappings)
			];
		}

		return $folderMap;
	}

	/**
	 * @return array<int, list<InternalFolderMapping>>
	 * @throws Exception
	 */
	private function getAllFolderMappings(): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('*')
			->from('group_folders_manage', 'g');

		$rows = $query->executeQuery()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];

			if (!isset($folderMap[$id])) {
				$folderMap[$id] = [$row];
			} else {
				$folderMap[$id][] = $row;
			}
		}

		return $folderMap;
	}

	/**
	 * @return array<int, InternalFolderMapping>
	 * @throws Exception
	 */
	private function getFolderMappings(int $id): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('group_folders_manage')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $query->executeQuery()->fetchAll();
	}

	/**
	 * @param InternalFolderMapping[] $mappings
	 * @return list<GroupFoldersAclManage>
	 */
	private function getManageAcl(array $mappings): array {
		return array_values(array_filter(array_map(function (array $entry): ?array {
			if ($entry['mapping_type'] === 'user') {
				$user = Server::get(IUserManager::class)->get($entry['mapping_id']);
				if ($user === null) {
					return null;
				}

				return [
					'type' => 'user',
					'id' => (string)$user->getUID(),
					'displayname' => (string)$user->getDisplayName()
				];
			}

			if ($entry['mapping_type'] === 'group') {
				$group = Server::get(IGroupManager::class)->get($entry['mapping_id']);
				if ($group === null) {
					return null;
				}

				return [
					'type' => 'group',
					'id' => $group->getGID(),
					'displayname' => $group->getDisplayName()
				];
			}

			if ($entry['mapping_type'] === 'circle') {
				$circle = $this->getCircle($entry['mapping_id']);
				if ($circle === null) {
					return null;
				}

				return [
					'type' => 'circle',
					'id' => $circle->getSingleId(),
					'displayname' => $circle->getDisplayName()
				];
			}

			return null;
		}, $mappings)));
	}

	/**
	 * @return ?InternalFolderOut
	 * @throws Exception
	 */
	public function getFolder(int $id, int $rootStorageId = 0): ?array {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'mount_point', 'quota', 'c.size', 'acl')
			->from('group_folders', 'f')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$this->joinQueryWithFileCache($query, $rootStorageId);

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if (!$row) {
			return null;
		}

		$folderMappings = $this->getFolderMappings($id);

		return [
			'id' => $id,
			'mount_point' => (string)$row['mount_point'],
			'groups' => $applicableMap[$id] ?? [],
			'quota' => $this->getRealQuota((int)$row['quota']),
			'size' => $row['size'] ?: 0,
			'acl' => (bool)$row['acl'],
			'manage' => $this->getManageAcl($folderMappings)
		];
	}

	/**
	 * Return just the ACL for the folder.
	 *
	 * @throws Exception
	 */
	public function getFolderAclEnabled(int $id): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('acl')
			->from('group_folders', 'f')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (bool)($row['acl'] ?? false);
	}

	public function getFolderByPath(string $path): int {
		/** @var Node $node */
		$node = Server::get(IRootFolder::class)->get($path);
		/** @var GroupMountPoint $mountPoint */
		$mountPoint = $node->getMountPoint();

		return $mountPoint->getFolderId();
	}

	/**
	 * @return array<int, array<string, GroupFoldersApplicable>>
	 * @throws Exception
	 */
	private function getAllApplicable(): array {
		$queryHelper = $this->getCirclesManager()?->getQueryHelper();

		$query = $queryHelper?->getQueryBuilder() ?? $this->connection->getQueryBuilder();
		$query->select('g.folder_id', 'g.group_id', 'g.circle_id', 'g.permissions')
			->from('group_folders_groups', 'g');

		$queryHelper?->addCircleDetails('g', 'circle_id');

		$rows = $query->executeQuery()->fetchAll();
		$applicableMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];
			if (!array_key_exists($id, $applicableMap)) {
				$applicableMap[$id] = [];
			}

			if (!$row['circle_id']) {
				$entityId = (string)$row['group_id'];

				$entry = [
					'displayName' => $row['group_id'],
					'permissions' => (int)$row['permissions'],
					'type' => 'group'
				];
			} else {
				$entityId = (string)$row['circle_id'];
				try {
					$circle = $queryHelper?->extractCircle($row);
				} catch (CircleNotFoundException) {
					$circle = null;
				}

				$entry = [
					'displayName' => $circle?->getDisplayName() ?? $row['circle_id'],
					'permissions' => (int)$row['permissions'],
					'type' => 'circle'
				];
			}

			$applicableMap[$id][$entityId] = $entry;
		}

		return $applicableMap;
	}

	/**
	 * @throws Exception
	 * @return list<GroupFoldersGroup>
	 */
	private function getGroups(int $id): array {
		$groups = $this->getAllApplicable()[$id] ?? [];
		$groups = array_map(fn (string $gid): ?IGroup => $this->groupManager->get($gid), array_keys($groups));

		return array_map(fn (IGroup $group): array => [
			'gid' => $group->getGID(),
			'displayname' => $group->getDisplayName()
		], array_values(array_filter($groups)));
	}

	/**
	 * @throws Exception
	 * @return list<GroupFoldersCircle>
	 */
	private function getCircles(int $id): array {
		$circles = $this->getAllApplicable()[$id] ?? [];
		$circles = array_map(fn (string $singleId): ?Circle => $this->getCircle($singleId), array_keys($circles));

		// get nested teams
		$nested = [];
		foreach ($circles as $circle) {
			try {
				$inherited = $circle?->getInheritedMembers(true) ?? [];
			} catch (\Exception $e) {
				$this->logger->notice('could not get nested teams', ['exception' => $e]);
				continue;
			}
			foreach ($inherited as $entry) {
				if ($entry->getUserType() === Member::TYPE_CIRCLE) {
					$nested[] = $entry->getBasedOn();
				}
			}
		}

		return array_map(fn (Circle $circle): array => [
			'sid' => $circle->getSingleId(),
			'displayname' => $circle->getDisplayName()
		], array_values(array_filter(array_merge($circles, $nested))));
	}

	/**
	 * Check if the user is able to configure the advanced folder permissions. This
	 * is the case if the user is an admin, has admin permissions for the group folder
	 * app or is member of a group that can manage permissions for the specific folder.
	 * @throws Exception
	 */
	public function canManageACL(int $folderId, IUser $user): bool {
		$userId = $user->getUId();
		if ($this->groupManager->isAdmin($userId)) {
			return true;
		}

		// Call private server api
		if (class_exists(\OC\Settings\AuthorizedGroupMapper::class)) {
			$authorizedGroupMapper = Server::get(\OC\Settings\AuthorizedGroupMapper::class);
			$settingClasses = $authorizedGroupMapper->findAllClassesForUser($user);
			if (in_array(\OCA\GroupFolders\Settings\Admin::class, $settingClasses, true)) {
				return true;
			}
		}

		$managerMappings = $this->getManagerMappings($folderId);
		return $this->userMappingManager->userInMappings($user, $managerMappings);
	}

	/**
	 * @param int $folderId
	 * @return IUserMapping[]
	 */
	private function getManagerMappings(int $folderId): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('mapping_type', 'mapping_id')
			->from('group_folders_manage')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$managerMappings = [];

		$rows = $query->executeQuery()->fetchAll();
		foreach ($rows as $manageRule) {
			$managerMappings[] = new UserMapping($manageRule['mapping_type'], $manageRule['mapping_id']);
		}
		return $managerMappings;
	}

	/**
	 * @throws Exception
	 * @return list<GroupFoldersGroup>
	 */
	public function searchGroups(int $id, string $search = ''): array {
		$groups = $this->getGroups($id);
		if ($search === '') {
			return $groups;
		}

		return array_values(array_filter($groups, fn (array $group): bool => (stripos($group['gid'], $search) !== false) || (stripos($group['displayname'], $search) !== false)));
	}

	/**
	 * @throws Exception
	 * @return list<GroupFoldersCircle>
	 */
	public function searchCircles(int $id, string $search = ''): array {
		$circles = $this->getCircles($id);
		if ($search === '') {
			return $circles;
		}

		return array_values(array_filter($circles, fn (array $circle): bool => (stripos($circle['displayname'], $search) !== false)));
	}

	/**
	 * @throws Exception
	 * @return list<GroupFoldersUser>
	 */
	public function searchUsers(int $id, string $search = '', int $limit = 10, int $offset = 0): array {
		$groups = $this->getGroups($id);
		$users = [];
		foreach ($groups as $groupArray) {
			$group = $this->groupManager->get($groupArray['gid']);
			if ($group) {
				$foundUsers = $this->groupManager->displayNamesInGroup($group->getGID(), $search, $limit, $offset);
				foreach ($foundUsers as $uid => $displayName) {
					if (!isset($users[$uid])) {
						$users[$uid] = [
							'uid' => (string)$uid,
							'displayname' => $displayName
						];
					}
				}
			}
		}

		foreach ($this->getCircles($id) as $circleData) {
			$circle = $this->getCircle($circleData['sid']);
			if ($circle === null) {
				continue;
			}

			foreach ($circle->getInheritedMembers(false) as $member) {
				if ($member->getUserType() !== Member::TYPE_USER) {
					continue;
				}

				$uid = $member->getUserId();
				if (!isset($users[$uid])) {
					$users[$uid] = [
						'uid' => $uid,
						'displayname' => $member->getDisplayName()
					];
				}
			}
		}

		return array_values($users);
	}

	/**
	 * @return list<InternalFolder>
	 * @throws Exception
	 */
	public function getFoldersForGroup(string $groupId, int $rootStorageId = 0): array {
		$query = $this->connection->getQueryBuilder();

		$query->select(
			'f.folder_id',
			'mount_point',
			'quota',
			'acl',
			'c.fileid',
			'c.storage',
			'c.path',
			'c.name',
			'c.mimetype',
			'c.mimepart',
			'c.size',
			'c.mtime',
			'c.storage_mtime',
			'c.etag',
			'c.encrypted',
			'c.parent'
		)
			->selectAlias('a.permissions', 'group_permissions')
			->selectAlias('c.permissions', 'permissions')
			->from('group_folders', 'f')
			->innerJoin(
				'f',
				'group_folders_groups',
				'a',
				$query->expr()->eq('f.folder_id', 'a.folder_id')
			)
			->where($query->expr()->eq('a.group_id', $query->createNamedParameter($groupId)));
		$this->joinQueryWithFileCache($query, $rootStorageId);

		$result = $query->executeQuery()->fetchAll();

		return array_values(array_map(fn (array $folder): array => [
			'folder_id' => (int)$folder['folder_id'],
			'mount_point' => (string)$folder['mount_point'],
			'permissions' => (int)$folder['group_permissions'],
			'quota' => $this->getRealQuota((int)$folder['quota']),
			'acl' => (bool)$folder['acl'],
			'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader) : null
		], $result));
	}

	/**
	 * @param string[] $groupIds
	 * @return list<InternalFolder>
	 * @throws Exception
	 */
	public function getFoldersForGroups(array $groupIds, int $rootStorageId = 0): array {
		$query = $this->connection->getQueryBuilder();

		$query->select(
			'f.folder_id',
			'mount_point',
			'quota',
			'acl',
			'c.fileid',
			'c.storage',
			'c.path',
			'c.name',
			'c.mimetype',
			'c.mimepart',
			'c.size',
			'c.mtime',
			'c.storage_mtime',
			'c.etag',
			'c.encrypted',
			'c.parent'
		)
			->selectAlias('a.permissions', 'group_permissions')
			->selectAlias('c.permissions', 'permissions')
			->from('group_folders', 'f')
			->innerJoin(
				'f',
				'group_folders_groups',
				'a',
				$query->expr()->eq('f.folder_id', 'a.folder_id')
			)
			->where($query->expr()->in('a.group_id', $query->createParameter('groupIds')));
		$this->joinQueryWithFileCache($query, $rootStorageId);

		// add chunking because Oracle can't deal with more than 1000 values in an expression list for in queries.
		$result = [];
		foreach (array_chunk($groupIds, 1000) as $chunk) {
			$query->setParameter('groupIds', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			$result = array_merge($result, $query->executeQuery()->fetchAll());
		}

		return array_map(fn (array $folder): array => [
			'folder_id' => (int)$folder['folder_id'],
			'mount_point' => (string)$folder['mount_point'],
			'permissions' => (int)$folder['group_permissions'],
			'quota' => $this->getRealQuota((int)$folder['quota']),
			'acl' => (bool)$folder['acl'],
			'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader) : null
		], array_values($result));
	}

	/**
	 * @return list<InternalFolder>
	 * @throws Exception
	 */
	public function getFoldersFromCircleMemberships(IUser $user, int $rootStorageId = 0): array {
		$circlesManager = $this->getCirclesManager();
		if ($circlesManager === null) {
			return [];
		}

		try {
			$federatedUser = $circlesManager->getLocalFederatedUser($user->getUID());
		} catch (\Exception) {
			return [];
		}

		$queryHelper = $circlesManager->getQueryHelper();
		$query = $queryHelper->getQueryBuilder();

		$query->select(
			'f.folder_id',
			'f.mount_point',
			'f.quota',
			'f.acl',
			'c.fileid',
			'c.storage',
			'c.path',
			'c.name',
			'c.mimetype',
			'c.mimepart',
			'c.size',
			'c.mtime',
			'c.storage_mtime',
			'c.etag',
			'c.encrypted',
			'c.parent'
		)
			->selectAlias('a.permissions', 'group_permissions')
			->selectAlias('c.permissions', 'permissions')
			->from('group_folders', 'f')
			->innerJoin(
				'f',
				'group_folders_groups',
				'a',
				$query->expr()->eq('f.folder_id', 'a.folder_id')
			)
			->where($query->expr()->neq('a.circle_id', $query->createNamedParameter('')));

		/** @psalm-suppress RedundantCondition */
		if (method_exists($queryHelper, 'limitToMemberships')) {
			$queryHelper->limitToMemberships('a', 'circle_id', $federatedUser);
		} else {
			$queryHelper->limitToInheritedMembers('a', 'circle_id', $federatedUser);
		}
		$this->joinQueryWithFileCache($query, $rootStorageId);

		return array_map(fn (array $folder): array => [
			'folder_id' => (int)$folder['folder_id'],
			'mount_point' => (string)$folder['mount_point'],
			'permissions' => (int)$folder['group_permissions'],
			'quota' => $this->getRealQuota((int)$folder['quota']),
			'acl' => (bool)$folder['acl'],
			'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader) : null
		], array_values($query->executeQuery()->fetchAll()));
	}


	/**
	 * @throws Exception
	 */
	public function createFolder(string $mountPoint): int {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders')
			->values([
				'mount_point' => $query->createNamedParameter($mountPoint),
				'quota' => self::SPACE_DEFAULT,
			]);
		$query->executeStatement();
		$id = $query->getLastInsertId();

		['storage_id' => $storageId, 'root_id' => $rootId] = $this->folderStorageManager->getRootAndStorageIdForFolder($id);
		$query->update('group_folders')
			->set('root_id', $query->createNamedParameter($rootId))
			->set('storage_id', $query->createNamedParameter($storageId))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('A new groupfolder "%s" was created with id %d', [$mountPoint, $id]));

		return $id;
	}

	/**
	 * @throws Exception
	 */
	public function addApplicableGroup(int $folderId, string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		if ($this->isACircle($groupId)) {
			$circleId = $groupId;
			$groupId = '';
		}

		$query->insert('group_folders_groups')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'group_id' => $query->createNamedParameter($groupId),
				'circle_id' => $query->createNamedParameter($circleId ?? ''),
				'permissions' => $query->createNamedParameter(Constants::PERMISSION_ALL)
			]);
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The group "%s" was given access to the groupfolder with id %d', [$groupId, $folderId]));
	}

	/**
	 * @throws Exception
	 */
	public function removeApplicableGroup(int $folderId, string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where(
				$query->expr()->eq(
					'folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$query->expr()->orX(
					$query->expr()->eq('group_id', $query->createNamedParameter($groupId)),
					$query->expr()->eq('circle_id', $query->createNamedParameter($groupId))
				)
			);
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The group "%s" was revoked access to the groupfolder with id %d', [$groupId, $folderId]));
	}


	/**
	 * @throws Exception
	 */
	public function setGroupPermissions(int $folderId, string $groupId, int $permissions): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders_groups')
			->set('permissions', $query->createNamedParameter($permissions, IQueryBuilder::PARAM_INT))
			->where(
				$query->expr()->eq(
					'folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$query->expr()->orX(
					$query->expr()->eq('group_id', $query->createNamedParameter($groupId)),
					$query->expr()->eq('circle_id', $query->createNamedParameter($groupId))
				)
			);

		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The permissions of group "%s" to the groupfolder with id %d was set to %d', [$groupId, $folderId, $permissions]));
	}

	/**
	 * @throws Exception
	 */
	public function setManageACL(int $folderId, string $type, string $id, bool $manageAcl): void {
		$query = $this->connection->getQueryBuilder();
		if ($manageAcl === true) {
			$query->insert('group_folders_manage')
				->values([
					'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
					'mapping_type' => $query->createNamedParameter($type),
					'mapping_id' => $query->createNamedParameter($id)
				]);
		} else {
			$query->delete('group_folders_manage')
				->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($type)))
				->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($id)));
		}

		$query->executeStatement();

		$action = $manageAcl ? 'given' : 'revoked';
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The %s "%s" was %s acl management rights to the groupfolder with id %d', [$type, $id, $action, $folderId]));
	}

	/**
	 * @throws Exception
	 */
	public function removeFolder(int $folderId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The groupfolder with id %d was removed', [$folderId]));
	}

	/**
	 * @throws Exception
	 */
	public function setFolderQuota(int $folderId, int $quota): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('quota', $query->createNamedParameter($quota))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The quota for groupfolder with id %d was set to %d bytes', [$folderId, $quota]));
	}

	/**
	 * @throws Exception
	 */
	public function renameFolder(int $folderId, string $newMountPoint): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('mount_point', $query->createNamedParameter($newMountPoint))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The groupfolder with id %d was renamed to "%s"', [$folderId, $newMountPoint]));
	}

	/**
	 * @throws Exception
	 */
	public function deleteGroup(string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
		$query->executeStatement();

		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_manage')
			->where($query->expr()->eq('mapping_id', $query->createNamedParameter($groupId)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter('group')));
		$query->executeStatement();

		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_acl')
			->where($query->expr()->eq('mapping_id', $query->createNamedParameter($groupId)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter('group')));
		$query->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function deleteCircle(string $circleId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where($query->expr()->eq('circle_id', $query->createNamedParameter($circleId)));
		$query->executeStatement();

		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_acl')
			->where($query->expr()->eq('mapping_id', $query->createNamedParameter($circleId)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter('circle')));
		$query->executeStatement();
	}


	/**
	 * @throws Exception
	 */
	public function setFolderACL(int $folderId, bool $acl): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('acl', $query->createNamedParameter((int)$acl, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
		$query->executeStatement();

		if ($acl === false) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('group_folders_manage')
				->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
			$query->executeStatement();
		}

		$action = $acl ? 'enabled' : 'disabled';
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('Advanced permissions for the groupfolder with id %d was %s', [$folderId, $action]));
	}

	/**
	 * @return list<InternalFolder>
	 * @throws Exception
	 */
	public function getFoldersForUser(IUser $user, int $rootStorageId = 0): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		$folders = array_merge(
			$this->getFoldersForGroups($groups, $rootStorageId),
			$this->getFoldersFromCircleMemberships($user, $rootStorageId)
		);

		$mergedFolders = [];
		foreach ($folders as $folder) {
			$id = $folder['folder_id'];
			if (isset($mergedFolders[$id])) {
				$mergedFolders[$id]['permissions'] |= $folder['permissions'];
			} else {
				$mergedFolders[$id] = $folder;
			}
		}

		return array_values($mergedFolders);
	}

	/**
	 * @throws Exception
	 */
	public function getFolderPermissionsForUser(IUser $user, int $folderId): int {
		$groups = $this->groupManager->getUserGroupIds($user);
		$folders = array_merge(
			$this->getFoldersForGroups($groups),
			$this->getFoldersFromCircleMemberships($user)
		);

		$permissions = 0;
		foreach ($folders as $folder) {
			if ($folderId === $folder['folder_id']) {
				$permissions |= $folder['permissions'];
			}
		}

		return $permissions;
	}

	/**
	 * returns if the groupId is in fact the singleId of an existing Circle
	 */
	public function isACircle(string $groupId): bool {
		return ($this->getCircle($groupId) !== null);
	}

	/**
	 * returns the Circle from its single Id, or NULL if not available
	 */
	public function getCircle(string $groupId): ?Circle {
		$circlesManager = $this->getCirclesManager();
		if ($circlesManager === null) {
			return null;
		}

		$circlesManager->startSuperSession();
		$probe = new CircleProbe();
		$probe->includeSystemCircles();
		$probe->includeSingleCircles();
		try {
			return $circlesManager->getCircle($groupId, $probe);
		} catch (CircleNotFoundException) {
		} catch (\Exception $e) {
			$this->logger->warning('', ['exception' => $e]);
		} finally {
			$circlesManager->stopSession();
		}

		return null;
	}

	public function getCirclesManager(): ?CirclesManager {
		try {
			return Server::get(CirclesManager::class);
		} catch (ContainerExceptionInterface|AutoloadNotAllowedException) {
			return null;
		}
	}

	private function getRealQuota(int $quota): int {
		if ($quota === self::SPACE_DEFAULT) {
			$defaultQuota = $this->config->getSystemValueInt('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED);
			// Prevent setting the default quota option to be the default quota value creating an unresolvable self reference
			if ($defaultQuota <= 0 && $defaultQuota !== FileInfo::SPACE_UNLIMITED) {
				throw new \Exception('Default Groupfolder quota value ' . $defaultQuota . ' is not allowed');
			}

			return $defaultQuota;
		}

		return $quota;
	}
}
