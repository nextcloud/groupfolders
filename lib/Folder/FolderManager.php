<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\Folder;

use OC\Files\Cache\Cache;
use OC\Files\Node\Node;
use OCA\Circles\CirclesManager;
use OCA\Circles\CirclesQueryHelper;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCP\AutoloadNotAllowedException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;

class FolderManager {
	public const ENTITY_GROUP = 1;
	public const ENTITY_CIRCLE = 2;

	public function __construct(
		private IDBConnection $connection,
		private IGroupManager $groupManager,
		private IMimeTypeLoader $mimeTypeLoader,
		private LoggerInterface $logger,
		private IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * @return (array|bool|int|mixed)[][]
	 *
	 * @psalm-return array<int, array{acl: bool, groups: array<array-key, array<array-key, int|string>>, id: int, mount_point: mixed, quota: int, size: 0}>
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
				'quota' => (int)$row['quota'],
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
		$query->leftJoin('f', 'filecache', 'c', $query->expr()->andX(
			// concat with empty string to work around missing cast to string
			$query->expr()->eq('c.name', $query->func()->concat('f.folder_id', $query->expr()->literal(""))),
			$query->expr()->eq('c.parent', $query->createNamedParameter($this->getGroupFolderRootId($rootStorageId)))
		));
	}

	/**
	 * @return (array|bool|int|mixed)[][]
	 *
	 * @psalm-return array<int, array{acl: bool, groups: array<string, array{displayName: string, type: string, permissions: integer}>, id: int, manage: array<array-key, array{displayname?: string, id?: string, type?: "group"|"user"|"circle"}>, mount_point: mixed, quota: int, size: int}>
	 * @throws Exception
	 */
	public function getAllFoldersWithSize(int $rootStorageId): array {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'mount_point', 'quota', 'size', 'acl')
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
				'quota' => (int)$row['quota'],
				'size' => $row['size'] ? (int)$row['size'] : 0,
				'acl' => (bool)$row['acl'],
				'manage' => $this->getManageAcl($mappings)
			];
		}

		return $folderMap;
	}

	/**
	 * @return (array|bool|int|mixed)[][]
	 *
	 * @psalm-return array<int, array{acl: bool, groups: array<string, array{displayName: string, type: string, permissions: integer}>, id: int, manage: array<array-key, array{displayname?: string, id?: string, type?: "group"|"user"|"circle"}>, mount_point: mixed, quota: int, size: int}>
	 * @throws Exception
	 */
	public function getAllFoldersForUserWithSize(int $rootStorageId, IUser $user): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('f.folder_id', 'mount_point', 'quota', 'size', 'acl')
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
				'quota' => (int)$row['quota'],
				'size' => $row['size'] ? (int)$row['size'] : 0,
				'acl' => (bool)$row['acl'],
				'manage' => $this->getManageAcl($mappings)
			];
		}

		return $folderMap;
	}

	/**
	 * @return array[]
	 *
	 * @psalm-return array<int, list<mixed>>
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
	 * @return array[]
	 *
	 * @psalm-return array<int, list<mixed>>
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
	 * @return array{type?: 'user'|'group', id?: string, displayname?: string}[]
	 */
	private function getManageAcl(array $mappings): array {
		return array_filter(array_map(function (array $entry): ?array {
			if ($entry['mapping_type'] === 'user') {
				$user = \OC::$server->get(IUserManager::class)->get($entry['mapping_id']);
				if ($user === null) {
					return null;
				}
				return [
					'type' => 'user',
					'id' => (string)$user->getUID(),
					'displayname' => (string)$user->getDisplayName()
				];
			}
			$group = \OC::$server->get(IGroupManager::class)->get($entry['mapping_id']);
			if ($group === null) {
				return [];
			}
			return [
				'type' => 'group',
				'id' => (string)$group->getGID(),
				'displayname' => (string)$group->getDisplayName()
			];
		}, $mappings), function (?array $element): bool {
			return $element !== null;
		});
	}

	/**
	 * @return array{id: mixed, mount_point: mixed, groups: array<string, array{displayName: string, type: string, permissions: integer}>, quota: int, size: int, acl: bool}|false
	 * @throws Exception
	 */
	public function getFolder(int $id, int $rootStorageId) {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'mount_point', 'quota', 'size', 'acl')
			->from('group_folders', 'f')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$this->joinQueryWithFileCache($query, $rootStorageId);

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		$folderMappings = $this->getFolderMappings($id);
		return $row ? [
			'id' => $id,
			'mount_point' => (string)$row['mount_point'],
			'groups' => $applicableMap[$id] ?? [],
			'quota' => (int)$row['quota'],
			'size' => $row['size'] ? $row['size'] : 0,
			'acl' => (bool)$row['acl'],
			'manage' => $this->getManageAcl($folderMappings)
		] : false;
	}

	/**
	 * Return just the ACL for the folder.
	 *
	 * @return bool
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
		$node = \OC::$server->get(IRootFolder::class)->get($path);
		/** @var GroupMountPoint $mountPoint */
		$mountPoint = $node->getMountPoint();
		return $mountPoint->getFolderId();
	}

	/**
	 * @return int[][]
	 *
	 * @psalm-return array<int, array<array-key, array<array-key, string|int>>>
	 * @throws Exception
	 */
	private function getAllApplicable(): array {
		$queryHelper = null;
		if ($this->isCirclesAvailable($circlesManager)) {
			$queryHelper = $circlesManager?->getQueryHelper();
		}

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

			$entry = $this->generateApplicableMapEntry($row, $queryHelper, $entityId);
			$applicableMap[$id][$entityId] = $entry;
		}

		return $applicableMap;
	}


	/**
	 * @param array $row the row from database
	 * @param CirclesQueryHelper|null $queryHelper
	 * @param string|null $entityId the type of the entity
	 *
	 * @return array{displayName: string, permissions: int, type: 'circle'|'group'}
	 */
	private function generateApplicableMapEntry(
		array $row,
		?CirclesQueryHelper $queryHelper = null,
		?string &$entityId = null
	): array {
		if (!$row['circle_id']) {
			$entityId = $row['group_id'];

			return [
				'displayName' => $row['group_id'],
				'permissions' => (int)$row['permissions'],
				'type' => 'group'
			];
		}

		$entityId = $row['circle_id'];
		try {
			$circle = $queryHelper?->extractCircle($row);
		} catch (CircleNotFoundException $e) {
			$circle = null;
		}
		$displayName = $circle?->getDisplayName() ?? $row['circle_id'];

		return [
			'displayName' => $displayName,
			'permissions' => (int)$row['permissions'],
			'type' => 'circle'
		];
	}


	/**
	 * @throws Exception
	 */
	private function getGroups(int $id): array {
		$groups = $this->getAllApplicable()[$id] ?? [];
		$groups = array_map(function ($gid) {
			return $this->groupManager->get($gid);
		}, array_keys($groups));
		return array_map(function ($group) {
			return [
				'gid' => $group->getGID(),
				'displayname' => $group->getDisplayName()
			];
		}, array_filter($groups));
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
		if (class_exists('\OC\Settings\AuthorizedGroupMapper')) {
			$authorizedGroupMapper = \OC::$server->get('\OC\Settings\AuthorizedGroupMapper');
			$settingClasses = $authorizedGroupMapper->findAllClassesForUser($user);
			if (in_array('OCA\GroupFolders\Settings\Admin', $settingClasses, true)) {
				return true;
			}
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('group_folders_manage')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter('user')))
			->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($userId)));
		if ($query->executeQuery()->rowCount() === 1) {
			return true;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('group_folders_manage')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter('group')));
		$groups = $query->executeQuery()->fetchAll();
		foreach ($groups as $manageRule) {
			if ($this->groupManager->isInGroup($userId, $manageRule['mapping_id'])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @throws Exception
	 */
	public function searchGroups(int $id, string $search = ''): array {
		$groups = $this->getGroups($id);
		if ($search === '') {
			return $groups;
		}
		return array_filter($groups, function ($group) use ($search) {
			return (stripos($group['gid'], $search) !== false) || (stripos($group['displayname'], $search) !== false);
		});
	}

	/**
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
							'uid' => $uid,
							'displayname' => $displayName
						];
					}
				}
			}
		}
		return array_values($users);
	}

	/**
	 * @param string $groupId
	 * @param int $rootStorageId
	 * @return list<array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}>
	 * @throws Exception
	 */
	public function getFoldersForGroup(string $groupId, int $rootStorageId = 0): array {
		$query = $this->connection->getQueryBuilder();

		$query->select(
			'f.folder_id',
			'mount_point',
			'quota',
			'acl',
			'fileid',
			'storage',
			'path',
			'name',
			'mimetype',
			'mimepart',
			'size',
			'mtime',
			'storage_mtime',
			'etag',
			'encrypted',
			'parent'
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
		return array_map(function ($folder): array {
			return [
				'folder_id' => (int)$folder['folder_id'],
				'mount_point' => (string)$folder['mount_point'],
				'permissions' => (int)$folder['group_permissions'],
				'quota' => (int)$folder['quota'],
				'acl' => (bool)$folder['acl'],
				'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader) : null
			];
		}, $result);
	}

	/**
	 * @param string[] $groupIds
	 * @param int $rootStorageId
	 * @return array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}[]
	 * @throws Exception
	 */
	public function getFoldersForGroups(array $groupIds, int $rootStorageId = 0): array {
		$query = $this->connection->getQueryBuilder();

		$query->select(
			'f.folder_id',
			'mount_point',
			'quota',
			'acl',
			'fileid',
			'storage',
			'path',
			'name',
			'mimetype',
			'mimepart',
			'size',
			'mtime',
			'storage_mtime',
			'etag',
			'encrypted',
			'parent'
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

		return array_map(function (array $folder): array {
			return [
				'folder_id' => (int)$folder['folder_id'],
				'mount_point' => (string)$folder['mount_point'],
				'permissions' => (int)$folder['group_permissions'],
				'quota' => (int)$folder['quota'],
				'acl' => (bool)$folder['acl'],
				'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader) : null
			];
		}, $result);
	}

	/**
	 * @param string[] $groupIds
	 * @param int $rootStorageId
	 * @return array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}[]
	 * @throws Exception
	 */
	public function getFoldersFromCircleMemberships(IUser $user, int $rootStorageId = 0): array {
		if (!$this->isCirclesAvailable($circlesManager)) {
			return [];
		}

		try {
			$federatedUser = $circlesManager->getLocalFederatedUser($user->getUID());
		} catch (\Exception $e) {
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
			  );

		$queryHelper->limitToInheritedMembers('a', 'circle_id', $federatedUser);
		$this->joinQueryWithFileCache($query, $rootStorageId);

		return array_map(function (array $folder): array {
			return [
				'folder_id' => (int)$folder['folder_id'],
				'mount_point' => (string)$folder['mount_point'],
				'permissions' => (int)$folder['group_permissions'],
				'quota' => (int)$folder['quota'],
				'acl' => (bool)$folder['acl'],
				'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader) : null
			];
		}, $query->executeQuery()->fetchAll());
	}


	/**
	 * @throws Exception
	 */
	public function createFolder(string $mountPoint): int {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders')
			->values([
				'mount_point' => $query->createNamedParameter($mountPoint)
			]);
		$query->executeStatement();
		$id = $query->getLastInsertId();

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

		$action = $manageAcl ? "given" : "revoked";
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

		$action = $acl ? "enabled" : "disabled";
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('Advanced permissions for the groupfolder with id %d was %s', [$folderId, $action]));
	}

	/**
	 * @param IUser $user
	 * @param int $rootStorageId
	 * @return array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}[]
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
	 * @param IUser $user
	 * @param int $folderId
	 * @return int
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
	 *
	 * @param string $groupId
	 *
	 * @return bool
	 */
	public function isACircle(string $groupId): bool {
		if (!$this->isCirclesAvailable($circlesManager)) {
			return false;
		}

		$circlesManager->startSuperSession();
		$probe = new CircleProbe();
		$probe->includeSystemCircles();
		$probe->includeSingleCircles();
		try {
			$circlesManager->getCircle($groupId, $probe);

			return true;
		} catch (CircleNotFoundException $e) {
		} catch (\Exception $e) {
			$this->logger->warning('', ['exception' => $e]);
		} finally {
			$circlesManager->stopSession();
		}

		return false;
	}

	/**
	 * returns if the circles manager is available.
	 * also set the parameter.
	 *
	 * @param CirclesManager|null $circlesManager
	 *
	 * @return bool
	 */
	public function isCirclesAvailable(?CirclesManager &$circlesManager = null): bool {
		try {
			/** @var CirclesManager $circlesManager */
			$circlesManager = Server::get(CirclesManager::class);
		} catch (ContainerExceptionInterface | AutoloadNotAllowedException $e) {
			return false;
		}

		return true;
	}
}
