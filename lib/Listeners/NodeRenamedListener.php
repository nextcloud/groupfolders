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
use Psr\Container\ContainerInterface;

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class NodeRenamedListener implements IEventListener {
	public function __construct(
		private readonly ContainerInterface $container,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
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

			/** @var TrashBackend $trashBackend */
			$trashBackend = $this->container->get(TrashBackend::class);
			$trashBackend->updateTrashedChildren($sourceParentStorage, $targetStorage, $sourceParentPath, $targetPath);
		}

		if ($hasVersionApp && $sourceFolder->id !== $targetFolder->id) {
			/** @var VersionsBackend $versionsBackend */
			$versionsBackend = $this->container->get(VersionsBackend::class);
			$versionsBackend->moveVersionsBetweenFolders($target, $sourceFolder, $targetFolder);
		}
	}
}
