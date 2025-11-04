<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base {
	public function __construct(
		private readonly FolderManager $folderManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('groupfolders:create')
			->setDescription('Create a new Team folder')
			->addArgument('name', InputArgument::REQUIRED, 'Name or mount point of the new folder')
			->addOption('bucket', null, InputOption::VALUE_REQUIRED, 'Overwrite the bucket used for the new folder')
			->addOption('acl-no-default-permission', null, InputOption::VALUE_NONE, 'Do not grant any advanced permission by default');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = trim((string)$input->getArgument('name'));

		// Check if the folder name is valid
		if (empty($name)) {
			$output->writeln('<error>Folder name cannot be empty</error>');
			return 1;
		}

		// Check if mount point already exists
		$folders = $this->folderManager->getAllFolders();
		foreach ($folders as $folder) {
			if ($folder->mountPoint === $name) {
				$output->writeln('<error>A Folder with the name ' . $name . ' already exists</error>');
				return 1;
			}
		}

		$options = [];
		if ($bucket = $input->getOption('bucket')) {
			$options['bucket'] = $bucket;
		}

		$aclDefaultNoPermission = (bool)$input->getOption('acl-no-default-permission');

		$id = $this->folderManager->createFolder($name, $options, $aclDefaultNoPermission);
		$output->writeln((string)$id);

		return 0;
	}
}
