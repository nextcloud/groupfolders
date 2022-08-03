<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Carl Schwan <carl@carlschwan.eu>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for commands asking the user for a folder id.
 */
abstract class FolderCommand extends Base {
	protected FolderManager $folderManager;
	protected IRootFolder $rootFolder;
	protected MountProvider $mountProvider;

	public function __construct(FolderManager $folderManager, IRootFolder $rootFolder, MountProvider $mountProvider) {
		parent::__construct();
		$this->folderManager = $folderManager;
		$this->rootFolder = $rootFolder;
		$this->mountProvider = $mountProvider;
	}

	/**
	 * @psalm-return array{id: mixed, mount_point: string, groups: array<empty, empty>|mixed, quota: int, size: int|mixed, acl: bool}|false
	 */
	protected function getFolder(InputInterface $input, OutputInterface $output) {
		$folderId = (int)$input->getArgument('folder_id');
		if ((string)$folderId !== $input->getArgument('folder_id')) {
			// Protect against removing folderId === 0 when typing a string (e.g. folder name instead of folder id)
			$output->writeln('<error>Folder id argument is not an integer. Got ' . $input->getArgument('folder_id') . '</error>');
			return false;
		}
		$folder = $this->folderManager->getFolder($folderId, $this->rootFolder->getMountPoint()->getNumericStorageId());
		if ($folder === false) {
			$output->writeln('<error>Folder not found: ' . $folderId . '</error>');
			return false;
		}
		return $folder;
	}
}
