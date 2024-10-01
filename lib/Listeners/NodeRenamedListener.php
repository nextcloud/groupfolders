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

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class NodeRenamedListener implements IEventListener {
	public function __construct(
		private TrashManager $trashManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeRenamedEvent) {
			return;
		}

		$target = $event->getTarget();
		if (!$target instanceof Folder) {
			return;
		}

		$targetStorage = $target->getStorage();
		if (!$targetStorage->instanceOfStorage(GroupFolderStorage::class)) {
			return;
		}

		$source = $event->getSource();
		// Look at the parent because the node itself is not existing anymore
		$sourceParent = $source->getParent();
		$sourceParentStorage = $sourceParent->getStorage();
		if (!$sourceParentStorage->instanceOfStorage(GroupFolderStorage::class)) {
			return;
		}

		// Get internal path on parent to avoid NotFoundException
		$sourceParentPath = $sourceParent->getInternalPath();
		if ($sourceParentPath !== '') {
			$sourceParentPath .= '/';
		}

		$sourceParentPath .= $source->getName();
		$targetPath = $target->getInternalPath();
		$this->trashManager->updateTrashedChildren($sourceParentStorage->getFolderId(), $targetStorage->getFolderId(), $sourceParentPath, $targetPath);
	}
}
