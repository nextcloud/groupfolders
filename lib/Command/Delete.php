<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Delete extends FolderCommand {
	protected function configure(): void {
		$this
			->setName('groupfolders:delete')
			->setDescription('Move Team folder to the recovery bin')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to delete')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation')
			->addOption('permanent', 'p', InputOption::VALUE_NONE, 'Permanently delete the Team folder and all files');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$folder = $this->getFolder($input, $output);
		if ($folder === null) {
			return -1;
		}

		/** @var QuestionHelper $helper */
		$helper = $this->getHelper('question');
		$permanent = (bool)$input->getOption('permanent');
		$question = new ConfirmationQuestion(
			$permanent
				? 'Are you sure you want to permanently delete the Team folder ' . $folder->mountPoint . ' and all files within, this cannot be undone (y/N).'
				: 'Move the Team folder ' . $folder->mountPoint . ' to the recovery bin? It can be restored for 30 days (y/N).',
			false,
		);
		if ($input->getOption('force') || $helper->ask($input, $output, $question)) {
			$this->folderManager->archiveFolder($folder->id, null);
			if ($permanent) {
				$this->folderManager->purgeDeletedFolder($folder->id);
			}
		}

		return 0;
	}
}
