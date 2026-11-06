<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\DropColumn;
use OCP\Migration\Attributes\DropIndex;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Removes the `team_circle_id` column and its unique index. The folders it
 * flagged were migrated to `managed_by` by Version2300000Date20261028000001.
 */
#[DropColumn('group_folders', 'team_circle_id', ColumnType::STRING, 'replaced by managed_by')]
#[DropIndex('group_folders', IndexType::UNIQUE, 'replaced by managed_by')]
class Version2300000Date20261028000002 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('group_folders');
		if ($table->hasIndex('group_folders_team_circle')) {
			$table->dropIndex('group_folders_team_circle');
		}

		if ($table->hasColumn('team_circle_id')) {
			$table->dropColumn('team_circle_id');
		}

		return $schema;
	}
}
