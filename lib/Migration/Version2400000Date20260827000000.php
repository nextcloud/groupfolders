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
 * Adds quotas for direct subfolders of Team folders.
 */
class Version2400000Date20260827000000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('gf_subfolder_quota')) {
			return null;
		}

		$table = $schema->createTable('gf_subfolder_quota');
		$table->addColumn('folder_id', Types::INTEGER, [
			'notnull' => true,
		]);
		$table->addColumn('file_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('quota', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->setPrimaryKey(['folder_id', 'file_id']);
		$table->addIndex(['file_id'], 'gf_subfolder_quota_file');

		return $schema;
	}
}
