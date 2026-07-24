<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Folder;

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class NodeRenamedListener implements IEventListener {
	public function __construct(
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		/** @phpstan-ignore instanceof.alwaysTrue */
		if (!$event instanceof NodeRenamedEvent) {
			return;
		}

		$hasVersionApp = interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class);
		$hasTrashApp = interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class);

		$target = $event->getTarget();

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

		$sourceFolder = $sourceParentStorage->getFolder();
		$targetFolder = $targetStorage->getFolder();

		if ($hasTrashApp && $target instanceof Folder) {
			// Get internal path on parent to avoid NotFoundException
			$sourceParentPath = $sourceParent->getInternalPath();
			if ($sourceParentPath !== '') {
				$sourceParentPath .= '/';
			}

			$sourceParentPath .= $source->getName();
			$targetPath = $target->getInternalPath();

			$trashBackend = Server::get(TrashBackend::class);
			$trashBackend->updateTrashedChildren($sourceParentStorage, $targetStorage, $sourceParentPath, $targetPath);
		}

		if ($hasVersionApp && $sourceFolder->id !== $targetFolder->id) {
			$this->versionsBackend->moveVersionsBetweenFolders($target, $sourceFolder, $targetFolder);
		}
	}
}
