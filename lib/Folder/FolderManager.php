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

use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FolderManager {
	/** @var IDBConnection */
	private $connection;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function getAllFolders() {
		$applicableMap = $this->getAllApplicable();

		$query = $this->connection->getQueryBuilder();

		$folderPath = $query->func()->concat($query->createNamedParameter('__groupfolders/'), 'folder_id');

		$query->select('folder_id', 'mount_point', 'quota', 'size')
			->from('group_folders', 'f')
			->leftJoin('f', 'filecache', 'c', $query->expr()->eq('path', $folderPath));

		$rows = $query->execute()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = $row['folder_id'];
			$folderMap[$id] = [
				'mount_point' => $row['mount_point'],
				'groups' => isset($applicableMap[$id]) ? $applicableMap[$id] : [],
				'quota' => $row['quota'],
				'size' => $row['size'] ? $row['size'] : 0
			];
		}

		return $folderMap;
	}

	private function getAllApplicable() {
		$query = $this->connection->getQueryBuilder();

		$query->select('folder_id', 'group_id', 'permissions')
			->from('group_folders_applicable');

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
	 * @return array [$mountPoint => $permissions]
	 */
	public function getFoldersForGroup($groupId) {
		$query = $this->connection->getQueryBuilder();

		$query->select('f.folder_id', 'mount_point', 'permissions', 'quota')
			->from('group_folders', 'f')
			->innerJoin('f', 'group_folders_applicable', 'a',
				$query->expr()->eq('f.folder_id', 'a.folder_id'))
			->where($query->expr()->eq('a.group_id', $query->createNamedParameter($groupId)));

		return $query->execute()->fetchAll();
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

		$query->insert('group_folders_applicable')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'group_id' => $query->createNamedParameter($groupId),
				'permissions' => $query->createNamedParameter(Constants::PERMISSION_ALL)
			]);
		$query->execute();
	}

	public function removeApplicableGroup($folderId, $groupId) {
		$query = $this->connection->getQueryBuilder();

		$query->delete('group_folders_applicable')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('group_id', $query->createNamedParameter($groupId)));
		$query->execute();
	}

	public function setGroupPermissions($folderId, $groupId, $permissions) {
		$query = $this->connection->getQueryBuilder();

		$query->update('group_folders_applicable')
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
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->execute();
	}
}
