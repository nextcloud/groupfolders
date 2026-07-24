<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use OCA\GroupFolders\Trash\TrashBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * One-off repair to find trash items whose `group_folders_trash` row was
 * updated to point to a new Team folder but the actual entries weren't.
 */
class RepairMisplacedTrashItemsJob extends QueuedJob {
	public function __construct(
		private readonly TrashBackend $trashBackend,
		private readonly LoggerInterface $logger,
		ITimeFactory $timeFactory,
	) {
		parent::__construct($timeFactory);
	}

	public function getName(): string {
		return 'Repair team folder trash item to their correct storage';
	}

	#[\Override]
	public function run(mixed $argument): void {
		$fixed = $this->trashBackend->repairMisplacedTrashItems();
		if ($fixed > 0) {
			$this->logger->info("Moved $fixed team folder trash item(s) to their correct storage");
		}
	}
}
