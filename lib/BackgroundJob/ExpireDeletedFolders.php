<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\BackgroundJob;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Permanently removes Team folders whose recovery period has elapsed.
 */
class ExpireDeletedFolders extends TimedJob {
	public function __construct(
		private readonly ITimeFactory $timeFactory,
		private readonly FolderManager $folderManager,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(24 * 60 * 60);
		$this->setAllowParallelRuns(false);
	}

	#[\Override]
	protected function run(mixed $argument): void {
		$expiredBefore = $this->timeFactory->getTime() - FolderManager::DELETED_FOLDER_RETENTION_SECONDS;

		foreach ($this->folderManager->getDeletedFoldersBefore($expiredBefore) as $deletedFolder) {
			try {
				$this->folderManager->purgeDeletedFolder($deletedFolder->folder->id);
			} catch (\Throwable $exception) {
				$this->logger->error('Could not permanently remove an expired Team folder from the recovery bin', [
					'app' => 'groupfolders',
					'folderId' => $deletedFolder->folder->id,
					'exception' => $exception,
				]);
			}
		}
	}
}
