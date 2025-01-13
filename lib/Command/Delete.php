<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Delete extends FolderCommand {
	protected function configure(): void {
		$this
			->setName('groupfolders:delete')
			->setDescription('Delete Team folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to rename')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$folder = $this->getFolder($input, $output);
		if ($folder === null) {
			return -1;
		}

		$helper = $this->getHelper('question');
		$question = new ConfirmationQuestion('Are you sure you want to delete the Team folder ' . $folder['mount_point'] . ' and all files within, this cannot be undone (y/N).', false);
		if ($input->getOption('force') || $helper->ask($input, $output, $question)) {
			$folderMount = $this->mountProvider->getFolder($folder['id']);
			$this->folderManager->removeFolder($folder['id']);
			$folderMount->delete();
		}

		return 0;
	}
}
