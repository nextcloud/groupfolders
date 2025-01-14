<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\Trashbin;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\App\IAppManager;
use OCP\Server;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Cleanup extends Base {
	private ?TrashBackend $trashBackend = null;

	public function __construct(
		private FolderManager $folderManager,
	) {
		parent::__construct();
		if (Server::get(IAppManager::class)->isEnabledForUser('files_trashbin')) {
			$this->trashBackend = \OCP\Server::get(TrashBackend::class);
		}
	}

	protected function configure(): void {
		$this
			->setName('groupfolders:trashbin:cleanup')
			->setDescription('Empty the Team folder trashbin')
			->addArgument('folder_id', InputArgument::OPTIONAL, 'Id of the Team folder')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$this->trashBackend) {
			$output->writeln('<error>files_trashbin is disabled: Team folders trashbin is not available</error>');
			return -1;
		}

		/** @var QuestionHelper $helper */
		$helper = $this->getHelper('question');

		$folders = $this->folderManager->getAllFolders();
		if ($input->getArgument('folder_id') !== null) {
			$folderId = (int)$input->getArgument('folder_id');

			foreach ($folders as $folder) {
				if ($folder['id'] === $folderId) {
					$question = new ConfirmationQuestion('Are you sure you want to empty the trashbin of your Team folder with id ' . $folderId . ', this can not be undone (y/N).', false);
					if (!$input->getOption('force') && !$helper->ask($input, $output, $question)) {
						return -1;
					}

					$this->trashBackend->cleanTrashFolder($folder['id']);

					return 0;
				}
			}

			$output->writeln('<error>Folder not found: ' . $folderId . '</error>');

			return -1;
		} else {
			$question = new ConfirmationQuestion('Are you sure you want to empty the trashbin of your Team folders, this can not be undone (y/N).', false);
			if (!$input->getOption('force') && !$helper->ask($input, $output, $question)) {
				return -1;
			}

			foreach ($folders as $folder) {
				$this->trashBackend->cleanTrashFolder($folder['id']);
			}
		}

		return 0;
	}
}
