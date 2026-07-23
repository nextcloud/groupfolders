<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * One-off repair to find trash items whose `group_folders_trash` row was
 * updated to point to a new Team folder but the actual entries weren't.
 */
class Version2300000Date20260722115802 extends SimpleMigrationStep {
	public function __construct(
		private readonly TrashBackend $trashBackend,
	) {
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$fixed = $this->trashBackend->repairMisplacedTrashItems();
		if ($fixed > 0) {
			$output->info("Moved $fixed team folder trash item(s) to their correct storage");
		}
	}
}
