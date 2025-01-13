<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version201000Date20190111132839 extends SimpleMigrationStep {
	public function name(): string {
		return 'Add groupfolder_acl table';
	}

	public function description(): string {
		return 'Adds table to store ACL information for Team folders';
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('group_folders_acl')) {
			$table = $schema->createTable('group_folders_acl');
			$table->addColumn('acl_id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('fileid', 'bigint', [
				'notnull' => true,
			]);
			$table->addColumn('mapping_type', 'string', [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('mapping_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('mask', 'smallint', [
				'notnull' => true,
			]);
			$table->addColumn('permissions', 'smallint', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['acl_id']);
			$table->addIndex(['mapping_type', 'mapping_id'], 'groups_folder_acl_mapping');
			$table->addUniqueIndex(['fileid', 'mapping_type', 'mapping_id'], 'groups_folder_acl_unique');
		}

		return $schema;
	}
}
