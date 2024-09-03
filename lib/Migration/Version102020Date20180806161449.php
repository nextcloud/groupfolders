<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version102020Date20180806161449 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('group_folders')) {
			$table = $schema->createTable('group_folders');
			$table->addColumn('folder_id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('mount_point', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('quota', 'bigint', [
				'notnull' => true,
				'length' => 6,
				// Removed in migration Version19000Date20240903062631
				//'default' => -3,
			]);
			$table->setPrimaryKey(['folder_id']);
		}

		if (!$schema->hasTable('group_folders_groups')) {
			$table = $schema->createTable('group_folders_groups');
			$table->addColumn('applicable_id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('permissions', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('group_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['applicable_id']);
			$table->addIndex(['group_id'], 'group_folder_value');
			$table->addUniqueIndex(['folder_id', 'group_id'], 'groups_folder_group');
		}
		return $schema;
	}
}
