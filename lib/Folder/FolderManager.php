<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OC\Files\Cache\Cache;
use OC\Files\Node\Node;
use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\AppInfo\Application;
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
use OCP\IAppConfig;
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
 * @psalm-type InternalFolderMapping = array{
 *   folder_id: int,
 *   mapping_type: 'user'|'group'|'circle',
 *   mapping_id: string,
 * }
 */
class FolderManager {
	public const SPACE_DEFAULT = -4;

	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IGroupManager $groupManager,
		private readonly IMimeTypeLoader $mimeTypeLoader,
		private readonly LoggerInterface $logger,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IConfig $config,
		private readonly IUserMappingManager $userMappingManager,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly IAppConfig $appConfig,
	) {
	}

	/**
	 * @return array<int, FolderDefinitionWithMappings>
	 * @throws Exception
	 */
	public function getAllFolders(): array {
		$applicableMap = $this->getAllApplicable();
		$folderMappings = $this->getAllFolderMappings();

		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'mount_point', 'quota', 'acl', 'acl_default_no_permission', 'storage_id', 'root_id', 'options')
			->from('group_folders', 'f');

		$rows = $query->executeQuery()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$folder = $this->rowToFolder($row);
			$id = $folder->id;
			$folderMap[$id] = FolderDefinitionWithMappings::fromFolder(
				$folder,
				$applicableMap[$id] ?? [],
				$this->getManageAcl($folderMappings[$id] ?? []),
			);
		}

		return $folderMap;
	}

	private function selectWithFileCache(?IQueryBuilder $query = null): IQueryBuilder {
		if (!$query) {
			$query = $this->connection->getQueryBuilder();
		}

		$query->select(
			'f.folder_id',
			'mount_point',
			'quota',
			'acl',
			'acl_default_no_permission',
			'storage_id',
			'root_id',
			'options',
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
			'c.parent',
		)
			->selectAlias('c.permissions', 'permissions')
			->from('group_folders', 'f')
			->leftJoin('f', 'filecache', 'c', $query->expr()->eq('c.fileid', 'f.root_id'));
		return $query;
	}

	/**
	 * @return array<int, FolderWithMappingsAndCache>
	 * @throws Exception
	 */
	public function getAllFoldersWithSize(): array {
		$applicableMap = $this->getAllApplicable();

		$query = $this->selectWithFileCache();

		$rows = $query->executeQuery()->fetchAll();

		$folderMappings = $this->getAllFolderMappings();

		$folderMap = [];
		foreach ($rows as $row) {
			$folder = $this->rowToFolder($row);
			$id = $folder->id;
			$folderMap[$id] = FolderWithMappingsAndCache::fromFolderWithMapping(
				FolderDefinitionWithMappings::fromFolder(
					$folder,
					$applicableMap[$id] ?? [],
					$this->getManageAcl($folderMappings[$id] ?? []),
				),
				Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
			);
		}

		return $folderMap;
	}

	/**
	 * @return array<int, FolderWithMappingsAndCache>
	 * @throws Exception
	 */
	public function getAllFoldersForUserWithSize(IUser $user): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		$applicableMap = $this->getAllApplicable();

		$query = $this->selectWithFileCache();
		$query->innerJoin(
			'f',
			'group_folders_groups',
			'a',
			$query->expr()->eq('f.folder_id', 'a.folder_id'),
		)
			->selectAlias('a.permissions', 'group_permissions')
			->where($query->expr()->in('a.group_id', $query->createNamedParameter($groups, IQueryBuilder::PARAM_STR_ARRAY)));

		$rows = $query->executeQuery()->fetchAll();

		$folderMappings = $this->getAllFolderMappings();

		$folderMap = [];
		foreach ($rows as $row) {
			$folder = $this->rowToFolder($row);
			$id = $folder->id;
			$folderMap[$id] = FolderWithMappingsAndCache::fromFolderWithMapping(
				FolderDefinitionWithMappings::fromFolder(
					$folder,
					$applicableMap[$id] ?? [],
					$this->getManageAcl($folderMappings[$id] ?? []),
				),
				Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
			);
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
			switch ($entry['mapping_type']) {
				case 'user':
					$user = Server::get(IUserManager::class)->get($entry['mapping_id']);
					if ($user === null) {
						return null;
					}

					return [
						'type' => 'user',
						'id' => $user->getUID(),
						'displayname' => $user->getDisplayName(),
					];
				case 'group':
					$group = $this->groupManager->get($entry['mapping_id']);
					if ($group === null) {
						return null;
					}

					return [
						'type' => 'group',
						'id' => $group->getGID(),
						'displayname' => $group->getDisplayName(),
					];
				case 'circle':
					$circle = $this->getCircle($entry['mapping_id']);
					if ($circle === null) {
						return null;
					}

					return [
						'type' => 'circle',
						'id' => $circle->getSingleId(),
						'displayname' => $circle->getDisplayName(),
					];
			}

			return null;
		}, $mappings)));
	}

	public function getFolder(int $id): ?FolderWithMappingsAndCache {
		$applicableMap = $this->getAllApplicable();

		$query = $this->selectWithFileCache();

		$query->where($query->expr()->eq('f.folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if (!$row) {
			return null;
		}

		$folderMappings = $this->getFolderMappings($id);

		$folder = $this->rowToFolder($row);
		$id = $folder->id;
		return FolderWithMappingsAndCache::fromFolderWithMapping(
			FolderDefinitionWithMappings::fromFolder(
				$folder,
				$applicableMap[$id] ?? [],
				$this->getManageAcl($folderMappings),
			),
			Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
		);
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
					'displayName' => $this->groupManager->get($row['group_id'])?->getDisplayName() ?? $row['group_id'],
					'permissions' => (int)$row['permissions'],
					'type' => 'group',
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
					'type' => 'circle',
				];
			}

			$applicableMap[$id][$entityId] = $entry;
		}

		return $applicableMap;
	}

	/**
	 * @return list<GroupFoldersGroup>
	 * @throws Exception
	 */
	private function getGroups(int $id): array {
		$groups = $this->getAllApplicable()[$id] ?? [];
		$groups = array_map($this->groupManager->get(...), array_keys($groups));

		return array_map(fn (IGroup $group): array => [
			'gid' => $group->getGID(),
			'displayname' => $group->getDisplayName(),
		], array_values(array_filter($groups)));
	}

	/**
	 * @return list<GroupFoldersCircle>
	 * @throws Exception
	 */
	private function getCircles(int $id): array {
		$circles = $this->getAllApplicable()[$id] ?? [];
		$circles = array_map($this->getCircle(...), array_keys($circles));

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
			'displayname' => $circle->getDisplayName(),
		], array_values(array_filter(array_merge($circles, $nested))));
	}

	/**
	 * Check if the user is able to configure the advanced folder permissions. This
	 * is the case if the user is an admin, has admin permissions for the group folder
	 * app or is member of a group that can manage permissions for the specific folder.
	 *
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
	 * @return list<GroupFoldersGroup>
	 * @throws Exception
	 */
	public function searchGroups(int $id, string $search = ''): array {
		$groups = $this->getGroups($id);
		if ($search === '') {
			return $groups;
		}

		return array_values(array_filter($groups, fn (array $group): bool => (stripos($group['gid'], $search) !== false) || (stripos($group['displayname'], $search) !== false)));
	}

	/**
	 * @return list<GroupFoldersCircle>
	 * @throws Exception
	 */
	public function searchCircles(int $id, string $search = ''): array {
		$circles = $this->getCircles($id);
		if ($search === '') {
			return $circles;
		}

		return array_values(array_filter($circles, fn (array $circle): bool => (stripos($circle['displayname'], $search) !== false)));
	}

	/**
	 * @return list<GroupFoldersUser>
	 * @throws Exception
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
							'displayname' => $displayName,
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
						'displayname' => $member->getDisplayName(),
					];
				}
			}
		}

		return array_values($users);
	}

	private function getFolderOptions(array $row): array {
		if (!isset($row['options'])) {
			return [];
		}

		try {
			$options = json_decode($row['options'], true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			$this->logger->warning('Error while decoding the folder options', ['exception' => $e, 'folder_id' => $row['folder_id'] ?? 'unknown']);
			return [];
		}

		if (!is_array($options)) {
			return [];
		}

		return $options;
	}

	private function rowToFolder(array $row): FolderDefinition {
		return new FolderDefinition(
			(int)$row['folder_id'],
			(string)$row['mount_point'],
			$this->getRealQuota((int)$row['quota']),
			(bool)$row['acl'],
			(bool)$row['acl_default_no_permission'],
			(int)$row['storage_id'],
			(int)$row['root_id'],
			$this->getFolderOptions($row),
		);
	}

	/**
	 * @param string[] $groupIds
	 * @return list<FolderDefinitionWithPermissions>
	 * @throws Exception
	 */
	public function getFoldersForGroups(array $groupIds, ?int $folderId = null): array {
		if (count($groupIds) === 0) {
			return [];
		}
		$query = $this->selectWithFileCache();

		$query->innerJoin(
			'f',
			'group_folders_groups',
			'a',
			$query->expr()->eq('f.folder_id', 'a.folder_id'),
		)
			->selectAlias('a.permissions', 'group_permissions')
			->where($query->expr()->in('a.group_id', $query->createParameter('groupIds')));

		if ($folderId !== null) {
			$query->andWhere($query->expr()->eq('f.folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		}

		// add chunking because Oracle can't deal with more than 1000 values in an expression list for in queries.
		$result = [];
		foreach (array_chunk($groupIds, 1000) as $chunk) {
			$query->setParameter('groupIds', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			$result = array_merge($result, $query->executeQuery()->fetchAll());
		}

		return array_values(array_map(function (array $row): FolderDefinitionWithPermissions {
			$folder = $this->rowToFolder($row);
			return FolderDefinitionWithPermissions::fromFolder(
				$folder,
				Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
				(int)$row['group_permissions']
			);
		}, $result));
	}

	/**
	 * @return list<FolderDefinitionWithPermissions>
	 * @throws Exception
	 */
	public function getFoldersFromCircleMemberships(IUser $user, ?int $folderId = null): array {
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
		$query = $this->selectWithFileCache($queryHelper->getQueryBuilder());

		$query->innerJoin(
			'f',
			'group_folders_groups',
			'a',
			$query->expr()->eq('f.folder_id', 'a.folder_id'),
		)
			->selectAlias('a.permissions', 'group_permissions')
			->where($query->expr()->neq('a.circle_id', $query->createNamedParameter('')));

		if ($folderId !== null) {
			$query->andWhere($query->expr()->eq('f.folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		}

		/** @psalm-suppress RedundantCondition */
		if (method_exists($queryHelper, 'limitToMemberships')) {
			$queryHelper->limitToMemberships('a', 'circle_id', $federatedUser);
		} else {
			$queryHelper->limitToInheritedMembers('a', 'circle_id', $federatedUser);
		}

		return array_values(array_map(function (array $row): FolderDefinitionWithPermissions {
			$folder = $this->rowToFolder($row);
			return FolderDefinitionWithPermissions::fromFolder(
				$folder,
				Cache::cacheEntryFromData($row, $this->mimeTypeLoader),
				$row['group_permissions']
			);
		}, $query->executeQuery()->fetchAll()));
	}


	/**
	 * @throws Exception
	 */
	public function createFolder(string $mountPoint, array $options = [], bool $aclDefaultNoPermission = false): int {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders')
			->values([
				'mount_point' => $query->createNamedParameter($mountPoint),
				'quota' => self::SPACE_DEFAULT,
				'acl_default_no_permission' => $query->createNamedParameter($aclDefaultNoPermission, IQueryBuilder::PARAM_BOOL),
				'options' => $query->createNamedParameter(json_encode([
					'separate-storage' => true,
				]))
			]);
		$query->executeStatement();
		$id = $query->getLastInsertId();

		['storage_id' => $storageId, 'root_id' => $rootId] = $this->folderStorageManager->initRootAndStorageForFolder($id, true, $options);
		$query->update('group_folders')
			->set('root_id', $query->createNamedParameter($rootId))
			->set('storage_id', $query->createNamedParameter($storageId))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id)));
		$query->executeStatement();

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('A new groupfolder "%s" was created with id %d', [$mountPoint, $id]));

		$this->updateOverwriteHomeFolders();

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
				'permissions' => $query->createNamedParameter(Constants::PERMISSION_ALL),
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
					'folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				),
			)
			->andWhere(
				$query->expr()->orX(
					$query->expr()->eq('group_id', $query->createNamedParameter($groupId)),
					$query->expr()->eq('circle_id', $query->createNamedParameter($groupId)),
				),
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
					'folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				),
			)
			->andWhere(
				$query->expr()->orX(
					$query->expr()->eq('group_id', $query->createNamedParameter($groupId)),
					$query->expr()->eq('circle_id', $query->createNamedParameter($groupId)),
				),
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
					'mapping_id' => $query->createNamedParameter($id),
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

		$this->updateOverwriteHomeFolders();
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

		$this->updateOverwriteHomeFolders();
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
	 * @return list<FolderDefinitionWithPermissions>
	 * @throws Exception
	 */
	public function getFoldersForUser(IUser $user, ?int $folderId = null): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		/** @var list<FolderDefinitionWithPermissions> $folders */
		$folders = array_merge(
			$this->getFoldersForGroups($groups, $folderId),
			$this->getFoldersFromCircleMemberships($user, $folderId),
		);

		/** @var array<int, FolderDefinitionWithPermissions> $mergedFolders */
		$mergedFolders = [];
		foreach ($folders as $folder) {
			$id = $folder->id;
			if (isset($mergedFolders[$id])) {
				$mergedFolders[$id] = $mergedFolders[$id]->withAddedPermissions($folder->permissions);
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
		/** @var list<FolderDefinitionWithPermissions> $folders */
		$folders = array_merge(
			$this->getFoldersForGroups($groups, $folderId),
			$this->getFoldersFromCircleMemberships($user, $folderId),
		);

		$permissions = 0;
		foreach ($folders as $folder) {
			if ($folderId === $folder->id) {
				$permissions |= $folder->permissions;
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

	/**
	 * Check if any mountpoint is configured that overwrite the home folder
	 */
	private function hasHomeFolderOverwriteMount(): bool {
		$builder = $this->connection->getQueryBuilder();
		$query = $builder->select('folder_id')
			->from('group_folders')
			->where($builder->expr()->eq('mount_point', $builder->createNamedParameter('/')))
			->setMaxResults(1);
		$result = $query->executeQuery();
		return $result->rowCount() > 0;
	}

	public function updateOverwriteHomeFolders(): void {
		$appIdsList = $this->appConfig->getValueArray('files', 'overwrites_home_folders');

		if ($this->hasHomeFolderOverwriteMount()) {
			if (!in_array(Application::APP_ID, $appIdsList)) {
				$appIdsList[] = Application::APP_ID;
				$this->appConfig->setValueArray('files', 'overwrites_home_folders', $appIdsList);
			}
		} else {
			if (in_array(Application::APP_ID, $appIdsList)) {
				$appIdsList = array_values(array_filter($appIdsList, fn ($v): bool => $v !== Application::APP_ID));
				$this->appConfig->setValueArray('files', 'overwrites_home_folders', $appIdsList);
			}
		}
	}

	public function hasFolderACLDefaultNoPermission(int $folderId): bool {
		$qb = $this->connection->getQueryBuilder();

		$query = $qb
			->select('acl_default_no_permission')
			->from('group_folders')
			->where($qb->expr()->eq('folder_id', $qb->createNamedParameter($folderId)));

		$result = $query->executeQuery();
		$hasDefaultNoPermission = (bool)$result->fetchOne();
		$result->closeCursor();

		return $hasDefaultNoPermission;
	}
}
