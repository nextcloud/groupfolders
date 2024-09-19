<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\BackgroundJob;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class ExpireGroupVersions extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private GroupVersionsExpireManager $expireManager,
		private IAppConfig $appConfig,
		private FolderManager $folderManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run once per hour
		$this->setInterval(60 * 60);
		// But don't run if still running
		$this->setAllowParallelRuns(false);
	}

	/**
	 * @inheritDoc
	 * Expiring groupfolder versions can be quite expensive.
	 * We need to limit the amount of folders we expire per run.
	 */
	protected function run(mixed $argument): void {
		$lastFolder = $this->appConfig->getValueInt(Application::APP_ID, 'cron_last_folder_index', 0);
		$folders = $this->folderManager->getAllFolders();

		$folderCount = count($folders);
		$currentRunHour = (int)date('G', $this->time->getTime());

		// Calculate folders to process in the remaining hours, ensure at least one folder is processed
		$toDo = max(1, (int)ceil(($folderCount - $lastFolder) / (24 - $currentRunHour)));

		// If there are no folders, we don't need to do anything
		if ($folderCount === 0) {
			$this->logger->debug('No folders to expire', ['app' => 'cron']);
			return;
		}

		// If we would go over the end of the list, wrap around
		if ($lastFolder >= $folderCount) {
			$lastFolder = 0;
		}

		// Save the updated folder index BEFORE processing the folders
		$this->appConfig->setValueInt(Application::APP_ID, 'cron_last_folder_index', $lastFolder + $toDo);

		// Determine the set of folders to process
		$folderSet = array_slice($folders, $lastFolder, $toDo);
		$folderIDs = array_map(fn (array $folder): int => $folder['id'], $folderSet);

		// Log and start the expiration process
		$this->logger->debug('Expiring versions for ' . count($folderSet) . ' folders', ['app' => 'cron', 'folders' => $folderIDs]);
		$this->expireManager->expireFolders($folderSet);
	}
}
