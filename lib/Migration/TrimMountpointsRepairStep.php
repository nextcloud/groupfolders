<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class TrimMountpointsRepairStep implements IRepairStep {
	public function __construct(
		private readonly FolderManager $manager,
	) {

	}

	#[\Override]
	public function getName(): string {
		return 'Fix invalid Team folders mountpoints';
	}

	#[\Override]
	public function run(IOutput $output): void {
		foreach ($this->manager->getAllFolders() as $id => $folder) {
			$newMountpoint = $this->manager->trimMountpoint($folder->mountPoint);
			if ($newMountpoint !== $folder->mountPoint) {
				$this->manager->renameFolder($id, $newMountpoint);
			}
		}
	}
}
