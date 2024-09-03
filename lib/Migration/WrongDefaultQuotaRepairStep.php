<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\DB\Exception;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class WrongDefaultQuotaRepairStep implements IRepairStep {
	public function __construct(
		private FolderManager $manager,
	) {

	}

	public function getName() {
		return 'Adjust Groupfolders with wrong default quotas';
	}

	/**
	 * @param IOutput $output
	 * @throws Exception
	 */
	public function run(IOutput $output): void {
		foreach ($this->manager->getAllFolders() as $id => $folder) {
			$quota = $folder['quota'];

			$changed = false;
			if ($quota === 1073741274) {
				$quota = 1024 ** 3;
				$changed = true;
			} elseif ($quota === 10737412742) {
				$quota = 1024 ** 3 * 10;
				$changed = true;
			}

			if ($changed) {
				$this->manager->setFolderQuota($id, $quota);
			}
		}
	}
}
