<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCA\Files_Versions\Versions\IVersion;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Trigger expiry of versions for files stored in group folders.
 */
class ExpireGroupVersions extends ExpireGroupBase {
	protected GroupVersionsExpireManager $expireManager;

	public function __construct(
		GroupVersionsExpireManager $expireManager
	) {
		parent::__construct();
		$this->expireManager = $expireManager;
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiry of versions for files stored in group folders');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->expireManager->listen(GroupVersionsExpireManager::class, 'enterFolder', function (array $folder) use ($output) {
			$output->writeln("<info>Expiring version in '{$folder['mount_point']}'</info>");
		});
		$this->expireManager->listen(GroupVersionsExpireManager::class, 'deleteVersion', function (IVersion $version) use ($output) {
			$id = $version->getRevisionId();
			$file = $version->getSourceFileName();
			$output->writeln("<info>Expiring version $id for '$file'</info>");
		});

		$this->expireManager->listen(GroupVersionsExpireManager::class, 'deleteFile', function ($id) use ($output) {
			$output->writeln("<info>Cleaning up versions for no longer existing file with id $id</info>");
		});

		$this->expireManager->expireAll();
		return 0;
	}
}
