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
 * Adds delegated administrators for direct Team folder children.
 */
class Version2400000Date20260827100000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('gf_subfolder_manager')) {
			return null;
		}

		$table = $schema->createTable('gf_subfolder_manager');
		$table->addColumn('folder_id', Types::INTEGER, [
			'notnull' => true,
		]);
		$table->addColumn('file_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('mapping_type', Types::STRING, [
			'notnull' => true,
			'length' => 16,
		]);
		$table->addColumn('mapping_id', Types::STRING, [
			'notnull' => true,
			// Keep this in sync with group_folders_manage. Apart from being
			// sufficient for user, group, and Circle ids, this keeps the
			// composite primary key portable to older MySQL installations.
			'length' => 64,
		]);
		$table->setPrimaryKey(['folder_id', 'file_id', 'mapping_type', 'mapping_id']);
		$table->addIndex(['file_id'], 'gf_subfolder_manager_file');
		$table->addIndex(['mapping_type', 'mapping_id'], 'gf_subfolder_manager_mapping');

		return $schema;
	}
}
