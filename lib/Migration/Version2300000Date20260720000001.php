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
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Introduces the `team_circle_id` column on `group_folders` so that the
 * "this folder belongs to a team" relationship is stored as an explicit,
 * indexed, queryable column.
 */
#[AddColumn('group_folders', 'team_circle_id', ColumnType::STRING, 'the circle single id this team space belongs to (null for regular team folders)')]
#[AddIndex('group_folders', IndexType::UNIQUE, 'unique team circle id to prevent a team owning multiple folders')]
class Version2300000Date20260720000001 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('group_folders');
		if (!$table->hasColumn('team_circle_id')) {
			$table->addColumn(
				'team_circle_id', Types::STRING,
				[
					'notnull' => false,
					'length' => 31,
					'default' => null,
				]
			);
		}

		if (!$table->hasIndex('group_folders_team_circle')) {
			$table->addUniqueIndex(['team_circle_id'], 'group_folders_team_circle');
		}

		return $schema;
	}
}
