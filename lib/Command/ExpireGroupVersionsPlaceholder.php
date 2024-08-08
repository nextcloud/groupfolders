<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpireGroupVersionsPlaceholder extends Base {
	public function __construct() {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiry of versions for files stored in group folders');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('<error>groupfolder version handling is only supported with Nextcloud 15 and up</error>');
		return 0;
	}
}
