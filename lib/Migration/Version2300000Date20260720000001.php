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
use OCP\IDBConnection;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\AddIndex;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\Attributes\IndexType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

/**
 * Introduces the `team_circle_id` column on `group_folders` so that the
 * "this folder belongs to a team" relationship is stored as an explicit,
 * indexed, queryable column instead of being buried in the `options` JSON
 * (`essential: true`, `circle_id: <id>`).
 *
 * The backfill in {@see postSchemaChange()} migrates every existing
 * essential team space to the new column and strips the obsolete keys
 * from the `options` JSON.
 */
#[AddColumn('group_folders', 'team_circle_id', ColumnType::STRING, 'the circle single id this team space belongs to (null for regular team folders)')]
#[AddIndex('group_folders', IndexType::UNIQUE, 'unique team circle id to prevent a team owning multiple folders')]
class Version2300000Date20260720000001 extends SimpleMigrationStep {
	public function __construct(
		private readonly IDBConnection $connection,
		private readonly LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('group_folders');
		if (!$table->hasColumn('team_circle_id')) {
			$table->addColumn(
				'team_circle_id', Types::STRING,
				[
					'notnull' => false,
					'length' => 31,
					'default' => null,
				]
			);
		}

		if (!$table->hasIndex('group_folders_team_circle')) {
			$table->addUniqueIndex(['team_circle_id'], 'group_folders_team_circle');
		}

		return $schema;
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Backfill: read the legacy `options.essential` + `options.circle_id` JSON
		// keys and migrate them to the new `team_circle_id` column. The obsolete
		// keys are stripped from the JSON to leave a clean `options` column.
		$query = $this->connection->getQueryBuilder();
		$query->select('folder_id', 'options')
			->from('group_folders');
		$result = $query->executeQuery();

		$migrated = 0;
		$skipped = 0;
		while (($row = $result->fetch()) !== false) {
			$folderIdRaw = $row['folder_id'] ?? null;
			$folderId = is_numeric($folderIdRaw) ? (int)$folderIdRaw : 0;
			$rawOptions = $row['options'] ?? null;
			if (!is_string($rawOptions) || $rawOptions === '') {
				continue;
			}

			try {
				$options = json_decode($rawOptions, true, 512, JSON_THROW_ON_ERROR);
			} catch (\JsonException $e) {
				$this->logger->warning(
					'Could not decode group_folders options during team_circle_id backfill',
					['folder_id' => $folderId, 'exception' => $e],
				);
				$skipped++;
				continue;
			}

			if (!is_array($options)) {
				continue;
			}

			$wasEssential = isset($options['essential']) && $options['essential'] === true;
			$legacyCircleIdRaw = $options['circle_id'] ?? null;
			$legacyCircleId = is_string($legacyCircleIdRaw) ? $legacyCircleIdRaw : '';

			if (!$wasEssential && $legacyCircleId === '') {
				continue;
			}

			unset($options['essential'], $options['circle_id']);

			if ($wasEssential && $legacyCircleId === '') {
				$this->logger->warning(
					'Essential team space has no circle_id in legacy options; leaving team_circle_id empty',
					['folder_id' => $folderId],
				);
			}

			$update = $this->connection->getQueryBuilder();
			$update->update('group_folders')
				->set('team_circle_id', $update->createNamedParameter($legacyCircleId === '' ? null : $legacyCircleId))
				->set('options', $update->createNamedParameter(json_encode($options, JSON_THROW_ON_ERROR)))
				->where($update->expr()->eq('folder_id', $update->createNamedParameter($folderId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		if ($migrated > 0 || $skipped > 0) {
			$this->logger->info('Migrated team space essential flags to team_circle_id column', [
				'migrated' => $migrated,
				'skipped' => $skipped,
			]);
		}
	}
}
