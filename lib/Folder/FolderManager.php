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
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class FolderManager {
	/** @var IDBConnection */
	private $connection;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IMimeTypeLoader */
	private $mimeTypeLoader;

	public function __construct(IDBConnection $connection, IGroupManager $groupManager = null, IMimeTypeLoader $mimeTypeLoader = null) {
		$this->connection = $connection;

		// files_fulltextsearch compatibility
		if (!$groupManager) {
			$groupManager = \OC::$server->get(IGroupManager::class);
		}
		if (!$mimeTypeLoader) {
			$mimeTypeLoader = \OC::$server->get(IMimeTypeLoader::class);
		}
		$this->groupManager = $groupManager;
		$this->mimeTypeLoader = $mimeTypeLoader;
	}

	/**
	 * @return (array|bool|int|mixed)[][]
	 *
	 * @psalm-return array<int, array{id: int, mount_point: mixed, groups: array<empty, empty>|array<array-key, int>, quota: int, size: int, acl: bool}>
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
			$query->expr()->eq('name', $query->func()->concat('f.folder_id', $query->expr()->literal(""))),
			$query->expr()->eq('parent', $query->createNamedParameter($this->getGroupFolderRootId($rootStorageId)))
		));
	}

	/**
	 * @return (array|bool|int|mixed)[][]
	 *
	 * @psalm-return array<int, array{id: int, mount_point: mixed, groups: array<empty, empty>|array<array-key, int>, quota: int, size: int, acl: bool, manage: mixed}>
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
	 * @return array[]
	 *
	 * @psalm-return array<int, list<mixed>>
	 * @throws Exception
	 */
	private function getAllFolderMappings(): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('group_folders_manage');
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

	private function getManageAcl(array $mappings): array {
		return array_filter(array_map(function ($entry) {
			if ($entry['mapping_type'] === 'user') {
				$user = \OC::$server->get(IUserManager::class)->get($entry['mapping_id']);
				if ($user === null) {
					return null;
				}
				return [
					'type' => 'user',
					'id' => $user->getUID(),
					'displayname' => $user->getDisplayName()
				];
			}
			$group = \OC::$server->get(IGroupManager::class)->get($entry['mapping_id']);
			if ($group === null) {
				return [];
			}
			return [
				'type' => 'group',
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName()
			];
		}, $mappings), function ($element) {
			return $element !== null;
		});
	}

	/**
	 * @return (array|bool|int|mixed)[]|false
	 *
	 * @psalm-return array{id: mixed, mount_point: mixed, groups: array<empty, empty>|mixed, quota: int, size: int|mixed, acl: bool}|false
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

		return $row ? [
			'id' => $id,
			'mount_point' => $row['mount_point'],
			'groups' => $applicableMap[$id] ?? [],
			'quota' => (int)$row['quota'],
			'size' => $row['size'] ? $row['size'] : 0,
			'acl' => (bool)$row['acl']
		] : false;
	}

	public function getFolderByPath(string $path): int {
		/** @var Node $node */
		$node = \OC::$server->get(IRootFolder::class)->get($path);
		/** @var GroupMountPoint $mountpoint */
		$mountPoint = $node->getMountPoint();
		return $mountPoint->getFolderId();
	}

	/**
	 * @return int[][]
	 *
	 * @psalm-return array<int, array<array-key, int>>
	 * @throws Exception
	 */
	private function getAllApplicable(): array {
		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'group_id', 'permissions')
			->from('group_folders_groups');

		$rows = $query->executeQuery()->fetchAll();

		$applicableMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['folder_id'];
			if (!isset($applicableMap[$id])) {
				$applicableMap[$id] = [];
			}
			$applicableMap[$id][$row['group_id']] = (int)$row['permissions'];
		}

		return $applicableMap;
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
		return array_map(function ($folder) {
			return [
				'folder_id' => (int)$folder['folder_id'],
				'mount_point' => $folder['mount_point'],
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
	 * @return list<array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}>
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

		return array_map(function ($folder) {
			return [
				'folder_id' => (int)$folder['folder_id'],
				'mount_point' => $folder['mount_point'],
				'permissions' => (int)$folder['group_permissions'],
				'quota' => (int)$folder['quota'],
				'acl' => (bool)$folder['acl'],
				'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader) : null
			];
		}, $result);
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

		return $query->getLastInsertId();
	}

	/**
	 * @throws Exception
	 */
	public function setMountPoint(int $folderId, string $mountPoint): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('mount_point', $query->createNamedParameter($mountPoint))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function addApplicableGroup(int $folderId, string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders_groups')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'group_id' => $query->createNamedParameter($groupId),
				'permissions' => $query->createNamedParameter(Constants::PERMISSION_ALL)
			]);
		$query->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function removeApplicableGroup(int $folderId, string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
		$query->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function setGroupPermissions(int $folderId, string $groupId, int $permissions): void {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders_groups')
			->set('permissions', $query->createNamedParameter($permissions, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));

		$query->executeStatement();
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
	}

	/**
	 * @throws Exception
	 */
	public function removeFolder(int $folderId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
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
	}

	/**
	 * @throws Exception
	 */
	public function deleteGroup(string $groupId): void {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
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
	}

	/**
	 * @param IUser $user
	 * @param int $rootStorageId
	 * @return array[]
	 * @throws Exception
	 */
	public function getFoldersForUser(IUser $user, int $rootStorageId = 0): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		$folders = $this->getFoldersForGroups($groups, $rootStorageId);

		$mergedFolders = [];
		foreach ($folders as $folder) {
			$id = (int)$folder['folder_id'];
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
		$folders = $this->getFoldersForGroups($groups);

		$permissions = 0;
		foreach ($folders as $folder) {
			if ($folderId === (int)$folder['folder_id']) {
				$permissions |= $folder['permissions'];
			}
		}

		return $permissions;
	}
}
