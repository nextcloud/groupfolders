<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1401000Date20230426112001 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// adding circle_id varchar(32) to group_folders_groups
		// remove unique key groups_folder_group
		$table = $schema->getTable('group_folders_groups');
		if (!$table->hasColumn('circle_id')) {
			$table->addColumn(
				'circle_id', Types::STRING,
				[
					'notnull' => false,
					'length' => 32,
					'default' => ''
				]
			);

			// we will recreate one in Version1401000Date20230426112002, including circle_id
			if ($table->hasIndex('groups_folder_group')) {
				$table->dropIndex('groups_folder_group');
			}
		}

		return $schema;
	}
}
