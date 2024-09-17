<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\Trash\TrashBackend;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpireGroupTrash extends ExpireGroupBase {
	public function __construct(
		private TrashBackend $trashBackend,
		private Expiration $expiration,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiration of the trashbin for files stored in group folders');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		[$count, $size] = $this->trashBackend->expire($this->expiration);
		$output->writeln("<info>Removed $count expired trashbin items</info>");

		return 0;
	}
}
