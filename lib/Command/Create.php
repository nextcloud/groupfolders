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
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base {
	private FolderManager $folderManager;

	public function __construct(FolderManager $folderManager) {
		parent::__construct();
		$this->folderManager = $folderManager;
	}

	protected function configure() {
		$this
			->setName('groupfolders:create')
			->setDescription('Create a new group folder')
			->addArgument('name', InputArgument::REQUIRED, 'Name of the new folder');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$id = $this->folderManager->createFolder($input->getArgument('name'));
		$output->writeln((string)$id);
		return 0;
	}
}
