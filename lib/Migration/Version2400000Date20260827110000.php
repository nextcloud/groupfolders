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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the recovery state for deleted Team folders.
 */
class Version2400000Date20260827110000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('group_folders')) {
			return null;
		}

		$table = $schema->getTable('group_folders');
		$changed = false;

		if (!$table->hasColumn('deleted_at')) {
			$table->addColumn('deleted_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('deleted_by')) {
			$table->addColumn('deleted_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$changed = true;
		}

		if (!$table->hasIndex('group_folders_deleted_at')) {
			$table->addIndex(['deleted_at'], 'group_folders_deleted_at');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
