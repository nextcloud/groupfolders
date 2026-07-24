<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * One-off repair to find trash items whose `group_folders_trash` row was
 * updated to point to a new Team folder but the actual entries weren't.
 */
class RepairMisplacedTrashItemsStep implements IRepairStep {
	public function __construct(
		private readonly IJobList $jobList,
		private readonly IAppConfig $appConfig,
	) {

	}

	#[\Override]
	public function getName(): string {
		return 'Repair team folder trash item to their correct storage';
	}

	#[\Override]
	public function run(IOutput $output): void {
		if ($this->appConfig->getAppValueBool('checked_for_incorrect_storage_for_trash_items')) {
			return;
		}

		$this->jobList->add(RepairMisplacedTrashItemsJob::class);

		$this->appConfig->setAppValueBool('checked_for_incorrect_storage_for_trash_items', true);
	}
}
