<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use RuntimeException;

class TrashManager {
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	/**
	 * @param int[] $folderIds
	 * @return list<array{trash_id: int, name: string, deleted_time: int, original_location: string, folder_id: int, file_id: ?int, deleted_by: ?string}>
	 */
	public function listTrashForFolders(array $folderIds): array {
		$query = $this->connection->getTypedQueryBuilder();

		$query->selectColumns('trash_id', 'name', 'deleted_time', 'original_location', 'folder_id', 'file_id', 'deleted_by')
			->from('group_folders_trash')
			->orderBy('deleted_time')
			->where($query->expr()->in('folder_id', $query->createNamedParameter($folderIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$rows = $query->executeQuery()->fetchAll();

		return array_map(function (array $row): array {
			if (is_string($row['trash_id'])) {
				$row['trash_id'] = (int)$row['trash_id'];
			}
			if (!is_int($row['trash_id'])) {
				throw new RuntimeException('trash_id is not an int');
			}
			if (!is_string($row['name'])) {
				throw new RuntimeException('name is not a string');
			}
			if (is_string($row['deleted_time'])) {
				$row['deleted_time'] = (int)$row['deleted_time'];
			}
			if (!is_int($row['deleted_time'])) {
				throw new RuntimeException('deleted_time is not an int');
			}
			if (!is_string($row['original_location'])) {
				throw new RuntimeException('original_location is not a string');
			}
			if (is_string($row['folder_id'])) {
				$row['folder_id'] = (int)$row['folder_id'];
			}
			if (!is_int($row['folder_id'])) {
				throw new RuntimeException('folder_id is not an int');
			}
			if ($row['file_id'] !== null) {
				if (is_string($row['file_id'])) {
					$row['file_id'] = (int)$row['file_id'];
				}
				if (!is_int($row['file_id'])) {
					throw new RuntimeException('file_id is not an int');
				}
			}
			if ($row['deleted_by'] !== null && !is_string($row['deleted_by'])) {
				throw new RuntimeException('deleted_by is not a string');
			}

			return [
				'trash_id' => $row['trash_id'],
				'name' => $row['name'],
				'deleted_time' => $row['deleted_time'],
				'original_location' => $row['original_location'],
				'folder_id' => $row['folder_id'],
				'file_id' => $row['file_id'],
				'deleted_by' => $row['deleted_by'],
			];
		}, $rows);
	}

	public function addTrashItem(int $folderId, string $name, int $deletedTime, string $originalLocation, int $fileId, string $deletedBy): void {
		$query = $this->connection->getTypedQueryBuilder();

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

	public function removeItem(int $folderId, string $name, int $deletedTime): void {
		$query = $this->connection->getTypedQueryBuilder();

		$query->delete('group_folders_trash')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('name', $query->createNamedParameter($name)))
			->andWhere($query->expr()->eq('deleted_time', $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}

	public function emptyTrashbin(int $folderId): void {
		$query = $this->connection->getTypedQueryBuilder();

		$query->delete('group_folders_trash')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}

	public function updateTrashedChildren(int $fromFolderId, int $toFolderId, string $fromLocation, string $toLocation): void {
		// Update deep children
		$query = $this->connection->getTypedQueryBuilder();
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
		$query = $this->connection->getTypedQueryBuilder();
		$query->update('group_folders_trash')
			->set('folder_id', $query->createNamedParameter($toFolderId, IQueryBuilder::PARAM_INT))
			->set('original_location', $query->createNamedParameter($toLocation))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($fromFolderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('original_location', $query->createNamedParameter($fromLocation, IQueryBuilder::PARAM_STR)));
		$query->executeStatement();
	}
}
