<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000000Date20210216085047 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('group_folders_acl');
		if ($table->hasIndex('groups_folder_acl_file')) {
			$table->dropIndex('groups_folder_acl_file');
		}

		$table = $schema->getTable('group_folders_groups');
		if ($table->hasIndex('group_folder')) {
			$table->dropIndex('group_folder');
		}

		$table = $schema->getTable('group_folders_trash');
		if ($table->hasIndex('groups_folder_trash_folder')) {
			$table->dropIndex('groups_folder_trash_folder');
		}

		if ($table->hasIndex('groups_folder_name')) {
			$table->dropIndex('groups_folder_name');
		}

		return $schema;
	}
}
