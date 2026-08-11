<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'groupfolders:rename',
	description: 'Rename Team folder',
)]
class Rename extends FolderCommand {
	public function __invoke(
		IOutput $output,
		#[Argument(name: 'folder_id', description: 'Id of the folder to rename')]
		string $folderId,
		#[Argument(description: 'New value name of the folder')]
		string $name,
	): ExitCode|int {
		$folder = $this->getFolder($folderId, $output);
		if ($folder === null) {
			return -1;
		}

		if ($folder->isTeamSpace()) {
			$output->writeln('<error>This folder belongs to a team and cannot be renamed; unlink it from its team first.</error>');
			return ExitCode::Failure;
		}

		// Check if the new name is valid
		$name = $this->folderManager->trimMountpoint($name);
		if (empty($name)) {
			$output->writeln('<error>Folder name cannot be empty</error>');
			return ExitCode::Failure;
		}

		// Check if the name actually changed
		if ($folder->mountPoint === $name) {
			$output->writeln('The name is already set to ' . $name);
			return ExitCode::Success;
		}

		if ($this->folderManager->mountPointExists($name)) {
			$output->writeln('<error>A Folder with the name ' . $name . ' already exists</error>');
			return ExitCode::Failure;
		}

		$this->folderManager->renameFolder($folder->id, $name);

		return ExitCode::Success;
	}
}
