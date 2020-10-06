<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\Trash;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrashManager {
	/** @var IDBConnection */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function listTrashForFolders(array $folderIds): array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['trash_id', 'name', 'deleted_time', 'original_location', 'folder_id'])
			->from('group_folders_trash')
			->where($query->expr()->in('folder_id', $query->createNamedParameter($folderIds, IQueryBuilder::PARAM_INT_ARRAY)));
		return $query->execute()->fetchAll();
	}

	public function addTrashItem(int $folderId, string $name, int $deletedTime, string $originalLocation, int $fileId) {
		$query = $this->connection->getQueryBuilder();
		$query->insert('group_folders_trash')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'name' => $query->createNamedParameter($name),
				'deleted_time' => $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT),
				'original_location' => $query->createNamedParameter($originalLocation),
				'file_id' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)
			]);
		$query->execute();
	}

	public function getTrashItemByFileId(int $fileId) {
		$query = $this->connection->getQueryBuilder();
		$query->select(['trash_id', 'name', 'deleted_time', 'original_location', 'folder_id'])
			->from('group_folders_trash')
			->where($query->expr()->in('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $query->execute()->fetch();
	}

	public function removeItem(int $folderId, string $name, int $deletedTime) {
		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_trash')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('name', $query->createNamedParameter($name)))
			->andWhere($query->expr()->eq('deleted_time', $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT)));
		$query->execute();
	}

	public function emptyTrashbin(int $folderId) {
		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_trash')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->execute();
	}
}
