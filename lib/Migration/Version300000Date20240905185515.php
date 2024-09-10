<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds the delete_by column to the group_folders_trash table
 */
class Version300000Date20240905185515 extends SimpleMigrationStep {

	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('group_folders_trash')) {
			return null;
		}

		$table = $schema->getTable('group_folders_trash');

		if ($table->hasColumn('deleted_by')) {
			return null;
		}

		$table->addColumn('deleted_by', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);

		return $schema;
	}
}
