<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCP\Constants;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\DataCleansing;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Introduces the `managed_by` column on `group_folders` to tell which
 * component manages a folder (regular group folders or teams) and migrates
 * the folders flagged through the former `team_circle_id` column.
 */
#[AddColumn('group_folders', 'managed_by', ColumnType::STRING, 'the component managing this folder: groupfolders or teams')]
#[AddIndex('group_folders', IndexType::INDEX, 'filter folders by the component managing them')]
#[DataCleansing('group_folders', 'flag the folders previously linked through team_circle_id as managed by teams')]
class Version2300000Date20261028000001 extends SimpleMigrationStep {
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('group_folders');
		if (!$table->hasColumn('managed_by')) {
			$table->addColumn(
				'managed_by', Types::STRING,
				[
					'notnull' => true,
					'length' => 64,
					'default' => FolderDefinition::MANAGED_BY_GROUPFOLDERS,
				]
			);
		}

		if (!$table->hasIndex('group_folders_managed_by')) {
			$table->addIndex(['managed_by'], 'group_folders_managed_by');
		}

		return $schema;
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->getTable('group_folders')->hasColumn('team_circle_id')) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('folder_id', 'team_circle_id')
			->from('group_folders')
			->where($query->expr()->isNotNull('team_circle_id'));
		$result = $query->executeQuery();
		/** @var list<array{folder_id: int|string, team_circle_id: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			$folderId = (int)$row['folder_id'];

			$update = $this->connection->getQueryBuilder();
			$update->update('group_folders')
				->set('managed_by', $update->createNamedParameter(FolderDefinition::MANAGED_BY_TEAMS))
				->where($update->expr()->eq('folder_id', $update->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
			$update->executeStatement();

			// The owning team is now derived from the applicable mapping, so it has to exist.
			$exists = $this->connection->getQueryBuilder();
			$exists->select('folder_id')
				->from('group_folders_groups')
				->where($exists->expr()->eq('folder_id', $exists->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
				->andWhere($exists->expr()->eq('circle_id', $exists->createNamedParameter($row['team_circle_id'])));
			$existsResult = $exists->executeQuery();
			$hasMapping = $existsResult->fetchOne() !== false;
			$existsResult->closeCursor();

			if (!$hasMapping) {
				$insert = $this->connection->getQueryBuilder();
				$insert->insert('group_folders_groups')
					->values([
						'folder_id' => $insert->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
						'group_id' => $insert->createNamedParameter(''),
						'circle_id' => $insert->createNamedParameter($row['team_circle_id']),
						'permissions' => $insert->createNamedParameter(Constants::PERMISSION_ALL, IQueryBuilder::PARAM_INT),
					]);
				$insert->executeStatement();
			}
		}
	}
}
