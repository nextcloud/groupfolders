<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[AddIndex('group_folders_groups', IndexType::INDEX, 'adding index on single circle id for better select')]
class Version2000000Date20250128110101 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// we recreate the unique key, including circle_id
		$table = $schema->getTable('group_folders_groups');
		if (!$table->hasIndex('groups_folder_circle')) {
			$table->addIndex(['circle_id'], 'groups_folder_circle');
		}

		return $schema;
	}
}
