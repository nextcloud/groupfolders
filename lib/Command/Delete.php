<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IInput;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'groupfolders:delete',
	description: 'Delete Team folder',
)]
class Delete extends FolderCommand {
	public function __invoke(
		IOutput $output,
		IInput $input,
		#[Argument(name: 'folder_id', description: 'Id of the folder to rename')]
		string $folderId,
		#[Option(description: 'Skip confirmation', shortcut: 'f')]
		bool $force = false,
	): ExitCode|int {
		$folder = $this->getFolder($folderId, $output);
		if ($folder === null) {
			return -1;
		}

		if ($folder->isTeamSpace()) {
			$output->writeln('<error>This folder belongs to a team and cannot be deleted directly; unlink it from its team first.</error>');
			return ExitCode::Failure;
		}

		if ($force || $input->confirm('Are you sure you want to delete the Team folder ' . $folder->mountPoint . ' and all files within, this cannot be undone (y/N).', false)) {
			$this->folderStorageManager->deleteStoragesForFolder($folder);
			$this->folderManager->removeFolder($folder->id);
		}

		return ExitCode::Success;
	}
}
