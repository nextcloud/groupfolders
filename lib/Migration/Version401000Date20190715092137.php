<?php

declare(strict_types=1);

namespace OCA\GroupFolders\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version401000Date20190715092137 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('group_folders_groups');
		if (!$table->hasColumn('manage_acl')) {
			$table->addColumn('manage_acl', Type::BOOLEAN, [
				'notnull' => true,
				'default' => false
			]);
		}

		return $schema;
	}
}
