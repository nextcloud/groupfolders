<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

#[AsCommand(
	name: 'groupfolders:create',
	description: 'Create a new Team folder',
)]
class Create {
	public function __construct(
		private readonly FolderManager $folderManager,
	) {
	}

	public function __invoke(
		IOutput $output,
		#[Argument(description: 'Name or mount point of the new folder')]
		string $name,
		#[Option(description: 'Overwrite the bucket used for the new folder')]
		?string $bucket = null,
		#[Option(name: 'acl-no-default-permission', description: 'Do not grant any advanced permission by default')]
		bool $aclNoDefaultPermission = false,
	): ExitCode {
		$name = $this->folderManager->trimMountpoint($name);

		// Check if the folder name is valid
		if (empty($name)) {
			$output->writeln('<error>Folder name cannot be empty</error>');
			return ExitCode::Failure;
		}

		if ($this->folderManager->mountPointExists($name)) {
			$output->writeln('<error>A Folder with the name ' . $name . ' already exists</error>');
			return ExitCode::Failure;
		}

		$options = [];
		if ($bucket) {
			$options['bucket'] = $bucket;
		}

		$id = $this->folderManager->createFolder($name, $options, $aclNoDefaultPermission);
		$output->writeln((string)$id);

		return ExitCode::Success;
	}
}
