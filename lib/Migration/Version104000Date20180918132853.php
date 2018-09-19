<?php

declare(strict_types=1);

namespace OCA\GroupFolders\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version104000Date20180918132853 extends SimpleMigrationStep {
	public function name(): string {
		return 'Add group_folders_trash table';
	}

	public function description(): string {
		return 'Adds table to store trashbin information for group folders';
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('group_folders_trash')) {
			$table = $schema->createTable('group_folders_trash');
			$table->addColumn('trash_id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 250,
			]);
			$table->addColumn('original_location', 'string', [
				'notnull' => true,
				'length' => 4000,
			]);
			$table->addColumn('deleted_time', 'bigint', [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('folder_id', 'bigint', [
				'notnull' => true,
				'length' => 6,
			]);
			$table->setPrimaryKey(['trash_id']);
			$table->addIndex(['folder_id'], 'groups_folder_trash_folder');
			$table->addIndex(['folder_id', 'name'], 'groups_folder_name');
			$table->addUniqueIndex(['folder_id', 'name', 'deleted_time'], 'groups_folder_trash_unique');
		}

		return $schema;
	}
}
