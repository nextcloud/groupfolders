<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Trash\TrashManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Folder;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class NodeRenamedListener implements IEventListener {
	public function __construct(
		private TrashManager $trashManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		$source = $event->getSource();
		$target = $event->getTarget();
		// Look at the parent because the node itself is not existing anymore
		$sourceStorage = $source->getParent()->getStorage();
		$targetStorage = $target->getStorage();

		if (($target instanceof Folder) &&
			$sourceStorage->instanceOfStorage(GroupFolderStorage::class) &&
			$targetStorage->instanceOfStorage(GroupFolderStorage::class)) {
			// Get internal path on parent to avoid NotFoundException
			$sourcePath = $source->getParent()->getInternalPath();
			if ($sourcePath !== '') {
				$sourcePath .= '/';
			}
			$sourcePath .= $source->getName();
			$targetPath = $target->getInternalPath();
			$this->trashManager->updateTrashedChildren($sourceStorage->getFolderId(), $targetStorage->getFolderId(), $sourcePath, $targetPath);
		}
	}
}
