<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version103000Date20180806161724 extends SimpleMigrationStep {
	/** @var IDBConnection */
	private $connection;

	/** @var array */
	private $applicableData = [];

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @return void
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// copy data
		if ($schema->hasTable('group_folders_applicable')) {
			$query = $this->connection->getQueryBuilder();
			$query->select(['folder_id', 'permissions', 'group_id'])
				->from('group_folders_applicable');
			$result = $query->executeQuery();
			$this->applicableData = $result->fetchAll(\PDO::FETCH_ASSOC);
		}
	}

	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

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
			$table->addIndex(['folder_id'], 'groups_folder');
			$table->addIndex(['group_id'], 'groups_folder_value');
			$table->addUniqueIndex(['folder_id', 'group_id'], 'groups_folder_group');
		}

		if ($schema->hasTable('group_folders_applicable')) {
			$schema->dropTable('group_folders_applicable');
		}

		return $schema;
	}

	/**
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		if (count($this->applicableData)) {
			$query = $this->connection->getQueryBuilder();
			$query->insert('group_folders_groups')
				->values([
					'folder_id' => $query->createParameter('folder'),
					'group_id' => $query->createParameter('group'),
					'permissions' => $query->createParameter('permissions')
				]);

			foreach ($this->applicableData as $data) {
				$query->setParameter('folder', $data['folder_id']);
				$query->setParameter('group', $data['group_id']);
				$query->setParameter('permissions', $data['permissions']);

				$query->executeStatement();
			}
		}
	}
}
