<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version16000Date20230821085801 extends SimpleMigrationStep {
	/**
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('group_folders_versions')) {
			return null;
		}

		$table = $schema->createTable('group_folders_versions');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('file_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('timestamp', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('size', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('mimetype', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('metadata', Types::TEXT, [
			'notnull' => true,
			'default' => '{}',
		]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['file_id', 'timestamp'], 'gf_versions_uniq_index');

		return $schema;
	}
}
