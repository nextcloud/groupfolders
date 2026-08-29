<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Reads mount points straight from the folder table — no file cache join, no
 * mappings, no ACL — so a consumer can resolve a whole page of ids cheaply.
 */
class FolderNameResolver implements IFolderNameResolver {
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	public function getMountPoint(int $folderId): ?string {
		return $this->getMountPoints([$folderId])[$folderId] ?? null;
	}

	// Oracle rejects IN lists with more than 1000 expressions
	private const DB_CHUNK_SIZE = 1000;

	public function getMountPoints(array $folderIds): array {
		if ($folderIds === []) {
			return [];
		}
		$query = $this->connection->getQueryBuilder();
		$query->select('folder_id', 'mount_point')
			->from('group_folders')
			->where($query->expr()->in('folder_id', $query->createParameter('ids')));
		$mountPoints = [];
		foreach (array_chunk(array_values(array_unique($folderIds)), self::DB_CHUNK_SIZE) as $chunk) {
			$query->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$result = $query->executeQuery();
			while (($row = $result->fetch()) !== false) {
				$mountPoints[(int)$row['folder_id']] = (string)$row['mount_point'];
			}
			$result->closeCursor();
		}
		return $mountPoints;
	}
}
