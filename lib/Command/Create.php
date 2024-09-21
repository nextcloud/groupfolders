<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\DB\Exception;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Base {
	/**
	 * @throws LogicException
	 */
	public function __construct(
		private FolderManager $folderManager,
	) {
		parent::__construct();
	}

	/**
	 * @throws InvalidArgumentException
	 */
	protected function configure(): void {
		$this
			->setName('groupfolders:create')
			->setDescription('Create a new group folder')
			->addArgument('name', InputArgument::REQUIRED, 'Name of the new folder');
		parent::configure();
	}

	/**
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = $this->folderManager->createFolder($input->getArgument('name'));
		$output->writeln((string)$id);

		return 0;
	}
}
