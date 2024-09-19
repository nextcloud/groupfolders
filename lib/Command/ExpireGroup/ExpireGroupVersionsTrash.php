<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCP\EventDispatcher\IEventDispatcher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpireGroupVersionsTrash extends ExpireGroupVersions {
	public function __construct(
		GroupVersionsExpireManager $expireManager,
		IEventDispatcher $eventDispatcher,
		private TrashBackend $trashBackend,
		private Expiration $expiration,
	) {
		parent::__construct($expireManager, $eventDispatcher);
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiry of versions and trashbin for files stored in group folders');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		parent::execute($input, $output);

		[$count, $size] = $this->trashBackend->expire($this->expiration);
		$output->writeln("<info>Removed $count expired trashbin items</info>");

		return 0;
	}
}
