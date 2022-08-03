<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Command;

use OCP\Files\FileInfo;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Quota extends FolderCommand {
	protected function configure() {
		$this
			->setName('groupfolders:quota')
			->setDescription('Edit the quota of a configured group folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to configure')
			->addArgument('quota', InputArgument::REQUIRED, 'New value for the quota of the folder');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$folder = $this->getFolder($input, $output);
		if ($folder === false) {
			return -1;
		}
		$quotaString = strtolower($input->getArgument('quota'));
		$quota = ($quotaString === 'unlimited') ? FileInfo::SPACE_UNLIMITED : \OCP\Util::computerFileSize($quotaString);
		if ($quota) {
			$this->folderManager->setFolderQuota($folder['id'], (int)$quota);
			return 0;
		}
		$output->writeln('<error>Unable to parse quota input: ' . $quotaString . '</error>');
		return -1;
	}
}
