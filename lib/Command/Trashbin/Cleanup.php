<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\Trashbin;

use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\App\IAppManager;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IInput;
use OCP\Console\IOutput;
use OCP\Constants;
use OCP\Server;

#[AsCommand(
	name: 'groupfolders:trashbin:cleanup',
	description: 'Empty the Team folder trashbin',
)]
class Cleanup {
	private ?TrashBackend $trashBackend = null;

	public function __construct(
		private readonly FolderManager $folderManager,
	) {
		if (Server::get(IAppManager::class)->isEnabledForUser('files_trashbin')) {
			$this->trashBackend = Server::get(TrashBackend::class);
		}
	}

	public function __invoke(
		IOutput $output,
		IInput $input,
		#[Argument(name: 'folder_id', description: 'Id of the Team folder')]
		?string $folderId = null,
		#[Option(description: 'Skip confirmation', shortcut: 'f')]
		bool $force = false,
	): ExitCode|int {
		if (!$this->trashBackend) {
			$output->writeln('<error>files_trashbin is disabled: Team folders trashbin is not available</error>');
			return -1;
		}

		$folders = $this->folderManager->getAllFoldersWithSize();
		$folders = array_map(fn (FolderWithMappingsAndCache $folder): FolderDefinitionWithPermissions => FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL), $folders);

		if ($folderId !== null) {
			$folderIdInt = (int)$folderId;

			foreach ($folders as $folder) {
				if ($folder->id === $folderIdInt) {
					if (!$force && !$input->confirm('Are you sure you want to empty the trashbin of your Team folder with id ' . $folderIdInt . ', this can not be undone (y/N).', false)) {
						return -1;
					}

					$this->trashBackend->cleanTrashFolder($folder);

					return ExitCode::Success;
				}
			}

			$output->writeln('<error>Folder not found: ' . $folderIdInt . '</error>');

			return -1;
		} else {
			if (!$force && !$input->confirm('Are you sure you want to empty the trashbin of your Team folders, this can not be undone (y/N).', false)) {
				return -1;
			}

			foreach ($folders as $folder) {
				$this->trashBackend->cleanTrashFolder($folder);
			}
		}

		return ExitCode::Success;
	}
}
