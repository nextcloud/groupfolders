<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2400000Date20261109140000 extends SimpleMigrationStep {
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('group_folders_pool')) {
			$table = $schema->createTable('group_folders_pool');
			$table->addColumn('id', Types::STRING, ['notnull' => true, 'length' => 261]);
			$table->addColumn('group_id', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('team_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('limit_bytes', Types::BIGINT, ['notnull' => false]);
			$table->addColumn('revision', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
		} else {
			$table = $schema->getTable('group_folders_pool');
			if (!$table->hasColumn('team_id')) {
				$table->addColumn('team_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			}
		}
		if (!$schema->hasTable('group_folders_team_pool')) {
			$table = $schema->createTable('group_folders_team_pool');
			$table->addColumn('team_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('pool_id', Types::STRING, ['notnull' => true, 'length' => 261]);
			$table->setPrimaryKey(['team_id']);
			$table->addIndex(['pool_id'], 'gf_team_pool_id');
		}
		return $schema;
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('id')->from('group_folders_pool')
			->where($query->expr()->eq('id', $query->createNamedParameter('default')));
		$result = $query->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		if (!$exists) {
			$query = $this->connection->getQueryBuilder();
			$query->insert('group_folders_pool')->values([
				'id' => $query->createNamedParameter('default'),
				'revision' => $query->createNamedParameter(0),
			]);
			$query->executeStatement();
		}
	}
}
