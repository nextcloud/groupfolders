<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrashManager {
	public function __construct(
		private IDBConnection $connection,
	) {
		$this->connection = $connection;
	}

	/**
	 * @param int[] $folderIds
	 * @return array
	 */
	public function listTrashForFolders(array $folderIds): array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['trash_id', 'name', 'deleted_time', 'original_location', 'folder_id', 'file_id', 'deleted_by'])
			->from('group_folders_trash')
			->orderBy('deleted_time')
			->where($query->expr()->in('folder_id', $query->createNamedParameter($folderIds, IQueryBuilder::PARAM_INT_ARRAY)));
		return $query->executeQuery()->fetchAll();
	}

	public function addTrashItem(int $folderId, string $name, int $deletedTime, string $originalLocation, int $fileId, string $deletedBy): void {
		$query = $this->connection->getQueryBuilder();
		$query->insert('group_folders_trash')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'name' => $query->createNamedParameter($name),
				'deleted_time' => $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT),
				'original_location' => $query->createNamedParameter($originalLocation),
				'file_id' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
				'deleted_by' => $query->createNamedParameter($deletedBy),
			]);
		$query->executeStatement();
	}

	public function getTrashItemByFileId(int $fileId): ?array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['trash_id', 'name', 'deleted_time', 'original_location', 'folder_id', 'deleted_by'])
			->from('group_folders_trash')
			->where($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $query->executeQuery()->fetch() ?: null;
	}

	public function getTrashItemByFileName(int $folderId, string $name, int $deletedTime): ?array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['trash_id', 'name', 'deleted_time', 'original_location', 'folder_id', 'deleted_by'])
			->from('group_folders_trash')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('name', $query->createNamedParameter($name)))
			->andWhere($query->expr()->eq('deleted_time', $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT)));
		return $query->executeQuery()->fetch() ?: null;
	}

	public function removeItem(int $folderId, string $name, int $deletedTime): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_trash')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('name', $query->createNamedParameter($name)))
			->andWhere($query->expr()->eq('deleted_time', $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}

	public function emptyTrashbin(int $folderId): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_trash')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}

	public function updateTrashedChildren(int $fromFolderId, int $toFolderId, string $fromLocation, string $toLocation): void {
		// Update deep children
		$query = $this->connection->getQueryBuilder();
		$fun = $query->func();
		$sourceLength = mb_strlen($fromLocation);
		$newPathFunction = $fun->concat(
			$query->createNamedParameter($toLocation),
			$fun->substring('original_location', $query->createNamedParameter($sourceLength + 1, IQueryBuilder::PARAM_INT))// +1 for the ending slash
		);
		$query->update('group_folders_trash')
			->set('folder_id', $query->createNamedParameter($toFolderId, IQueryBuilder::PARAM_INT))
			->set('original_location', $newPathFunction)
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($fromFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->like('original_location', $query->createNamedParameter($this->connection->escapeLikeParameter($fromLocation) . '/%')));
		$query->executeStatement();

		// Update direct children
		$query = $this->connection->getQueryBuilder();
		$query->update('group_folders_trash')
			->set('folder_id', $query->createNamedParameter($toFolderId, IQueryBuilder::PARAM_INT))
			->set('original_location', $query->createNamedParameter($toLocation))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($fromFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('original_location', $query->createNamedParameter($fromLocation, IQueryBuilder::PARAM_STR)));
		$query->executeStatement();
	}
}
