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
use OCP\Console\ExitCode;
use OCP\Console\IOutput;
use OCP\EventDispatcher\IEventDispatcher;

/**
 * Trigger expiry of versions for files stored in group folders.
 */
class ExpireGroupVersions extends ExpireGroupBase {
	public function __construct(
		private readonly GroupVersionsExpireManager $expireManager,
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	#[\Override]
	public function __invoke(IOutput $output): ExitCode {
		$this->eventDispatcher->addListener(GroupVersionsExpireEnterFolderEvent::class, function (GroupVersionsExpireEnterFolderEvent $event) use ($output): void {
			$output->writeln("<info>Expiring version in '{$event->folder->mountPoint}'</info>");
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

		return ExitCode::Success;
	}
}
