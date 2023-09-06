<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Louis Chmn <louis@chmn.me>
 *
 * @author Louis Chmn <louis@chmn.me>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
