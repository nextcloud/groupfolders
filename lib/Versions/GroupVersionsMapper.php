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
	 * @return GroupVersionEntity[]
	 */
	public function findAllVersionsForFileId(int $fileId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			 ->from($this->getTableName())
			 ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		return $this->findEntities($qb);
	}

	/**
	 * @return GroupVersionEntity
	 */
	public function findCurrentVersionForFileId(int $fileId): GroupVersionEntity {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			 ->from($this->getTableName())
			 ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)))
			 ->orderBy('timestamp', 'DESC')
			 ->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findVersionForFileId(int $fileId, int $timestamp): GroupVersionEntity {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			 ->from($this->getTableName())
			 ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)))
			 ->andWhere($qb->expr()->eq('timestamp', $qb->createNamedParameter($timestamp)));

		return $this->findEntity($qb);
	}

	public function deleteAllVersionsForFileId(int $fileId): int {
		$qb = $this->db->getQueryBuilder();

		return $qb->delete($this->getTableName())
			 ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)))
			 ->executeStatement();
	}
}
