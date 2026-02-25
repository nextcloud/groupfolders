<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Versions;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<GroupVersionEntity>
 */
class GroupVersionsMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'group_folders_versions', GroupVersionEntity::class);
	}

	/**
	 * @return list<GroupVersionEntity>
	 */
	public function findAllVersionsForFileId(int $fileId): array {
		$qb = $this->db->getTypedQueryBuilder();

		$qb->selectColumns(...GroupVersionEntity::COLUMNS)
			->from($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findVersionForFileId(int $fileId, int $timestamp): GroupVersionEntity {
		$qb = $this->db->getTypedQueryBuilder();

		$qb->selectColumns(...GroupVersionEntity::COLUMNS)
			->from($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)))
			->andWhere($qb->expr()->eq('timestamp', $qb->createNamedParameter($timestamp)));

		return $this->findEntity($qb);
	}

	public function deleteAllVersionsForFileId(int $fileId): int {
		$qb = $this->db->getTypedQueryBuilder();

		return $qb->delete($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)))
			->executeStatement();
	}
}
