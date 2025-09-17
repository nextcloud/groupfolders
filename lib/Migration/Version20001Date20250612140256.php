<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Files\Config\IMountProviderCollection;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Adds root_id and options to the group folders table
 */
class Version20001Date20250612140256 extends SimpleMigrationStep {
	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IMountProviderCollection $mountProviderCollection,
	) {
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('group_folders')) {
			$table = $schema->getTable('group_folders');
			if (!$table->hasColumn('root_id')) {
				$table->addColumn('root_id', Types::BIGINT, ['notnull' => false]);
			}
			if (!$table->hasColumn('storage_id')) {
				$table->addColumn('storage_id', Types::BIGINT, ['notnull' => false]);
			}
			if (!$table->hasColumn('options')) {
				$table->addColumn('options', Types::TEXT, ['notnull' => false]);
			}
			return $schema;
		}
		return null;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$storageId = $this->getJailedGroupFolderStorageId();
		if ($storageId === null) {
			return;
		}
		$rootIds = $this->getJailedRootIds($storageId);
		if (count($rootIds) === 0) {
			return;
		}

		try {
			$this->connection->beginTransaction();

			$query = $this->connection->getQueryBuilder();
			$query->update('group_folders')
				->set('root_id', $query->createParameter('root_id'))
				->set('storage_id', $query->createNamedParameter($storageId))
				->where($query->expr()->eq('folder_id', $query->createParameter('folder_id')))
				// check for both NULL values (not migrated) and incorrect values from a broken migration
				->andWhere($query->expr()->neq('storage_id', $query->createNamedParameter($storageId)))
				// folders create before this migration have a NULL options
				->andWhere($query->expr()->isNull('options'));

			foreach ($rootIds as $folderId => $rootId) {
				$query->setParameter('root_id', $rootId);
				$query->setParameter('folder_id', $folderId);
				$query->executeStatement();
			}

			$this->connection->commit();
		} catch (\Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}
	}

	/**
	 * @return array<int, int>
	 */
	private function getJailedRootIds(int $storageId): array {
		$parentFolderId = $this->getJailedGroupFolderRootId($storageId);
		if ($parentFolderId === null) {
			return [];
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('name', 'fileid')
			->from('filecache')
			->where($query->expr()->eq('parent', $query->createNamedParameter($parentFolderId)))
			->andWhere($query->expr()->eq('storage', $query->createNamedParameter($storageId)));
		$result = $query->executeQuery();

		$rootIds = [];
		while ($row = $result->fetch()) {
			if (is_numeric($row['name'])) {
				$rootIds[(int)$row['name']] = $row['fileid'];
			}
		}
		return $rootIds;
	}

	private function getJailedGroupFolderRootId(int $storageId): ?int {
		$query = $this->connection->getQueryBuilder();
		$query->select('fileid')
			->from('filecache')
			->where($query->expr()->eq('path_hash', $query->createNamedParameter(md5('__groupfolders'))))
			->andWhere($query->expr()->eq('storage', $query->createNamedParameter($storageId)));

		$id = $query->executeQuery()->fetchOne();
		if ($id === false) {
			return null;
		} else {
			return (int)$id;
		}
	}

	private function getJailedGroupFolderStorageId(): ?int {
		$rootMounts = $this->mountProviderCollection->getRootMounts();
		foreach ($rootMounts as $rootMount) {
			if ($rootMount->getMountPoint() === '/') {
				return $rootMount->getNumericStorageId();
			}
		}
		return null;
	}
}
