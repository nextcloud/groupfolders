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
use OC\Files\Cache\CacheEntry;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;

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
			$groupManager = \OC::$server->getGroupManager();
		}
		if (!$mimeTypeLoader) {
			$mimeTypeLoader = \OC::$server->getMimeTypeLoader();
		}
		$this->groupManager = $groupManager;
		$this->mimeTypeLoader = $mimeTypeLoader;
	}

	public function getAllFolders() {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'mount_point', 'quota')
			->from('group_folders', 'f');

		$rows = $query->execute()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = $row['folder_id'];
			$folderMap[$id] = [
				'id' => $id,
				'mount_point' => $row['mount_point'],
				'groups' => isset($applicableMap[$id]) ? $applicableMap[$id] : [],
				'quota' => $row['quota'],
				'size' => 0
			];
		}

		return $folderMap;
	}

	public function getAllFoldersWithSize($rootStorageId) {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$folderPath = $query->func()->concat($query->createNamedParameter('__groupfolders/'), 'folder_id');

		$query->select('folder_id', 'mount_point', 'quota', 'size')
			->from('group_folders', 'f')
			->leftJoin('f', 'filecache', 'c', $query->expr()->andX(
				$query->expr()->eq('path_hash', $query->func()->md5($folderPath)),
				$query->expr()->eq('storage', $query->createNamedParameter($rootStorageId, IQueryBuilder::PARAM_INT))
			));

		$rows = $query->execute()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = $row['folder_id'];
			$folderMap[$id] = [
				'id' => $id,
				'mount_point' => $row['mount_point'],
				'groups' => isset($applicableMap[$id]) ? $applicableMap[$id] : [],
				'quota' => $row['quota'],
				'size' => $row['size'] ? $row['size'] : 0
			];
		}

		return $folderMap;
	}

	public function getFolder($id, $rootStorageId) {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$folderPath = $query->func()->concat($query->createNamedParameter('__groupfolders/'), 'folder_id');

		$query->select('folder_id', 'mount_point', 'quota', 'size')
			->from('group_folders', 'f')
			->leftJoin('f', 'filecache', 'c', $query->expr()->andX(
				$query->expr()->eq('path_hash', $query->func()->md5($folderPath)),
				$query->expr()->eq('storage', $query->createNamedParameter($rootStorageId, IQueryBuilder::PARAM_INT))
			))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$row = $query->execute()->fetch();

		return $row ? [
			'id' => $id,
			'mount_point' => $row['mount_point'],
			'groups' => isset($applicableMap[$id]) ? $applicableMap[$id] : [],
			'quota' => $row['quota'],
			'size' => $row['size'] ? $row['size'] : 0
		] : false;
	}

	private function getAllApplicable() {
		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'group_id', 'permissions')
			->from('group_folders_groups');

		$rows = $query->execute()->fetchAll();

		$applicableMap = [];
		foreach ($rows as $row) {
			$id = $row['folder_id'];
			if (!isset($applicableMap[$id])) {
				$applicableMap[$id] = [];
			}
			$applicableMap[$id][$row['group_id']] = $row['permissions'];
		}

		return $applicableMap;
	}

	/**
	 * @param string $groupId
	 * @param int $rootStorageId
	 * @return array[]
	 */
	public function getFoldersForGroup($groupId, $rootStorageId = 0) {
		$query = $this->connection->getQueryBuilder();

		$folderPath = $query->func()->concat($query->createNamedParameter('__groupfolders/'), 'f.folder_id');

		$query->select(
			'f.folder_id', 'mount_point', 'quota',
			'fileid', 'storage', 'path', 'name', 'mimetype', 'mimepart', 'size', 'mtime', 'storage_mtime', 'etag', 'encrypted', 'parent'
		)
			->selectAlias('a.permissions', 'group_permissions')
			->selectAlias('c.permissions', 'permissions')
			->from('group_folders', 'f')
			->innerJoin(
				'f',
				'group_folders_groups',
				'a',
				$query->expr()->eq('f.folder_id', 'a.folder_id')
			)->leftJoin('f', 'filecache', 'c', $query->expr()->andX(
				$query->expr()->eq('storage', $query->createNamedParameter($rootStorageId, IQueryBuilder::PARAM_INT)),
				$query->expr()->eq('path_hash', $query->func()->md5($folderPath))
			))
			->where($query->expr()->eq('a.group_id', $query->createNamedParameter($groupId)));

		$result = $query->execute()->fetchAll();
		return array_map(function ($folder) {
			return [
				'folder_id' => (int)$folder['folder_id'],
				'mount_point' => $folder['mount_point'],
				'permissions' => (int)$folder['group_permissions'],
				'quota' => (int)$folder['quota'],
				'rootCacheEntry' => (isset($folder['fileid'])) ? Cache::cacheEntryFromData($folder, $this->mimeTypeLoader): null
			];
		}, $result);
	}

	public function createFolder($mountPoint) {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders')
			->values([
				'mount_point' => $query->createNamedParameter($mountPoint)
			]);
		$query->execute();

		return $this->connection->lastInsertId('group_folders');
	}

	public function setMountPoint($folderId, $mountPoint) {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('mount_point', $query->createNamedParameter($mountPoint))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	public function addApplicableGroup($folderId, $groupId) {
		$query = $this->connection->getQueryBuilder();

		$query->insert('group_folders_groups')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'group_id' => $query->createNamedParameter($groupId),
				'permissions' => $query->createNamedParameter(Constants::PERMISSION_ALL)
			]);
		$query->execute();
	}

	public function removeApplicableGroup($folderId, $groupId) {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
		$query->execute();
	}

	public function setGroupPermissions($folderId, $groupId, $permissions) {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders_groups')
			->set('permissions', $query->createNamedParameter($permissions, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));

		$query->execute();
	}

	public function removeFolder($folderId) {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	public function setFolderQuota($folderId, $quota) {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('quota', $query->createNamedParameter($quota))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
		$query->execute();
	}

	public function renameFolder($folderId, $newMountPoint) {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders')
			->set('mount_point', $query->createNamedParameter($newMountPoint))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	public function deleteGroup($groupId) {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_groups')
			->where($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
		$query->execute();
	}

	/**
	 * @param IUser $user
	 * @param int $rootStorageId
	 * @return array[]
	 */
	public function getFoldersForUser(IUser $user, $rootStorageId = 0) {
		$groups = $this->groupManager->getUserGroupIds($user);
		$folders = array_reduce($groups, function ($folders, $groupId) use ($rootStorageId) {
			return array_merge($folders, $this->getFoldersForGroup($groupId, $rootStorageId));
		}, []);

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
}
