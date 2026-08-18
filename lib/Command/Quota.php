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
use OCP\Files\FileInfo;

#[AsCommand(
	name: 'groupfolders:quota',
	description: 'Edit the quota of a configured Team folder',
)]
class Quota extends FolderCommand {
	public function __invoke(
		IOutput $output,
		#[Argument(name: 'folder_id', description: 'Id of the folder to configure')]
		string $folderId,
		#[Argument(description: 'New value for the quota of the folder')]
		string $quota,
	): ExitCode|int {
		$folder = $this->getFolder($folderId, $output);
		if ($folder === null) {
			return -1;
		}

		$quotaString = strtolower($quota);
		$quotaValue = ($quotaString === 'unlimited') ? FileInfo::SPACE_UNLIMITED : \OCP\Util::computerFileSize($quotaString);
		if ($quotaValue) {
			$this->folderManager->setFolderQuota($folder->id, (int)$quotaValue);
			return ExitCode::Success;
		}

		$output->writeln('<error>Unable to parse quota input: ' . $quotaString . '</error>');

		return -1;
	}
}
