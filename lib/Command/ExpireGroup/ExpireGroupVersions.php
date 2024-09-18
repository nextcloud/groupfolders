<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCA\GroupFolders\Event\GroupVersionsExpireDeleteFileEvent;
use OCA\GroupFolders\Event\GroupVersionsExpireDeleteVersionEvent;
use OCA\GroupFolders\Event\GroupVersionsExpireEnterFolderEvent;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCP\EventDispatcher\IEventDispatcher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Trigger expiry of versions for files stored in group folders.
 */
class ExpireGroupVersions extends ExpireGroupBase {
	public function __construct(
		private GroupVersionsExpireManager $expireManager,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiry of versions for files stored in group folders');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->eventDispatcher->addListener(GroupVersionsExpireEnterFolderEvent::class, function (GroupVersionsExpireEnterFolderEvent $event) use ($output): void {
			$output->writeln("<info>Expiring version in '{$event->folder['mount_point']}'</info>");
		});
		$this->eventDispatcher->addListener(GroupVersionsExpireDeleteVersionEvent::class, function (GroupVersionsExpireDeleteVersionEvent $event) use ($output): void {
			$id = $event->version->getRevisionId();
			$file = $event->version->getSourceFileName();
			$output->writeln("<info>Expiring version $id for '$file'</info>");
		});

		$this->eventDispatcher->addListener(GroupVersionsExpireDeleteFileEvent::class, function (GroupVersionsExpireDeleteFileEvent $event) use ($output): void {
			$output->writeln('<info>Cleaning up versions for no longer existing file with id ' . $event->fileId . '</info>');
		});

		$this->expireManager->expireAll();

		return 0;
	}
}
