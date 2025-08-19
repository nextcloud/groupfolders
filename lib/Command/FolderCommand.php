<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for commands asking the user for a folder id.
 */
abstract class FolderCommand extends Base {

	public function __construct(
		protected FolderManager $folderManager,
		protected IRootFolder $rootFolder,
		protected MountProvider $mountProvider,
		protected FolderStorageManager $folderStorageManager,
	) {
		parent::__construct();
	}

	protected function getFolder(InputInterface $input, OutputInterface $output): ?FolderWithMappingsAndCache {
		$folderId = (int)$input->getArgument('folder_id');
		if ((string)$folderId !== $input->getArgument('folder_id')) {
			// Protect against removing folderId === 0 when typing a string (e.g. folder name instead of folder id)
			$output->writeln('<error>Folder id argument is not an integer. Got ' . $input->getArgument('folder_id') . '</error>');

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
