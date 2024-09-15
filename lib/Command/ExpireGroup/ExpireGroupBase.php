<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for the group folder expiration commands.
 */
class ExpireGroupBase extends Base {
	public function __construct() {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiration for files stored in group folders (trash and versions). Currently disabled.');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln('<error>groupfolder expiration handling is currently disabled because there is nothing to expire. Enable the "Delete Files" or/and "Versions" app to enable this feature.</error>');
		return 0;
	}
}
