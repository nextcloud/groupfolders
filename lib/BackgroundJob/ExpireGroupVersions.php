<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\BackgroundJob;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ExpireGroupVersions extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private GroupVersionsExpireManager $expireManager,
		private IConfig $config,
		private FolderManager $folderManager,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Run once per hour
		$this->setInterval(60 * 60);
		// But don't run if still running
		$this->setAllowParallelRuns(false);

		$this->expireManager = $expireManager;
	}

	/**
	 * Expiring groupfolder versions can be quite expensive.
	 * We need to limit the amount of folders we expire per run.
	 */
	protected function run($argument) {
		$lastFolder = (int)$this->config->getAppValue(Application::APP_ID, 'cron_last_folder_index', '0');
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
		$this->config->setAppValue(Application::APP_ID, 'cron_last_folder_index', (string)($lastFolder + $toDo));

		// Determine the set of folders to process
		$folderSet = array_slice($folders, $lastFolder, $toDo);
		$folderIDs = array_map(function ($folder) {
			return $folder['id'];
		}, $folderSet);

		// Log and start the expiration process
		$this->logger->debug('Expiring versions for ' . count($folderSet) . ' folders', ['app' => 'cron', 'folders' => $folderIDs]);
		$this->expireManager->expireFolders($folderSet);
	}
}
