<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Console\IOutput;
use OCP\Files\IRootFolder;

/**
 * Base command for commands asking the user for a folder id.
 */
abstract class FolderCommand {
	public function __construct(
		protected FolderManager $folderManager,
		protected IRootFolder $rootFolder,
		protected MountProvider $mountProvider,
		protected FolderStorageManager $folderStorageManager,
	) {
	}

	protected function getFolder(string $folderIdString, IOutput $output): ?FolderWithMappingsAndCache {
		$folderId = (int)$folderIdString;
		if ((string)$folderId !== $folderIdString) {
			// Protect against removing folderId === 0 when typing a string (e.g. folder name instead of folder id)
			$output->writeln('<error>Folder id argument is not an integer. Got ' . $folderIdString . '</error>');

			return null;
		}

		$folder = $this->folderManager->getFolder($folderId);
		if ($folder === null) {
			$output->writeln('<error>Folder not found: ' . $folderId . '</error>');
			return null;
		}

		return $folder;
	}
}
