<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1401000Date20230426112002 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// we recreate the unique key, including circle_id
		$table = $schema->getTable('group_folders_groups');
		if (!$table->hasIndex('groups_folder_group')) {
			$table->addUniqueIndex(['folder_id', 'circle_id', 'group_id'], 'groups_folder_group');
		}

		return $schema;
	}
}
