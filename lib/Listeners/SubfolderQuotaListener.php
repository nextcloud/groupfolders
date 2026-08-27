<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\SubfolderQuotaManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Exceptions\AbortedEventException;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Node;
use OCP\Files\NotFoundException;

/**
 * Keeps subfolder quota assignments attached to direct Team folder children.
 *
 * @template-implements IEventListener<Event>
 */
class SubfolderQuotaListener implements IEventListener {
	/**
	 * The source node in NodeRenamedEvent may no longer exist. Keep the source
	 * file id from the corresponding before event so a cross-storage move can
	 * remove the original assignment safely.
	 *
	 * @var array<string, array{folderId: int, fileId: int}>
	 */
	private array $pendingMoves = [];

	public function __construct(
		private readonly SubfolderQuotaManager $subfolderQuotaManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof BeforeNodeRenamedEvent) {
			$this->preventNestedConfiguredSubfolder($event);
			return;
		}

		if ($event instanceof NodeRenamedEvent) {
			$this->removeQuotaAfterMoveOutsideDirectChildren($event);
			return;
		}

		if (!$event instanceof NodeDeletedEvent) {
			return;
		}

		$this->removeQuotaAfterDeletion($event);
	}

	private function preventNestedConfiguredSubfolder(BeforeNodeRenamedEvent $event): void {
		$source = $event->getSource();
		$sourceStorage = $source->getStorage();
		if (!$sourceStorage->instanceOfStorage(GroupFolderStorage::class)) {
			return;
		}

		/** @var GroupFolderStorage $sourceStorage */
		$sourceFolder = $sourceStorage->getFolder();
		if (!$this->subfolderQuotaManager->hasSubfolderQuota($sourceFolder->id, $source->getId())) {
			return;
		}

		$target = $event->getTarget();
		$targetStorage = $target->getStorage();
		if ($targetStorage->instanceOfStorage(GroupFolderStorage::class)
			&& $targetStorage->getFolderId() === $sourceFolder->id
			&& $targetStorage->appliesSubfolderQuotas()
			&& !$this->isDirectChildOf($target, $sourceFolder)) {
			throw new AbortedEventException('A subfolder with a quota must remain a direct child of its Team folder');
		}

		$this->pendingMoves[$this->getMoveKey($source, $target)] = [
			'folderId' => $sourceFolder->id,
			'fileId' => $source->getId(),
		];
	}

	private function removeQuotaAfterMoveOutsideDirectChildren(NodeRenamedEvent $event): void {
		$source = $event->getSource();
		$target = $event->getTarget();
		$pendingMoveKey = $this->getMoveKey($source, $target);
		$pendingMove = $this->pendingMoves[$pendingMoveKey] ?? null;
		unset($this->pendingMoves[$pendingMoveKey]);

		if ($pendingMove === null) {
			return;
		}

		$targetStorage = $target->getStorage();
		$remainsDirectChild = $targetStorage->instanceOfStorage(GroupFolderStorage::class)
			&& $targetStorage->getFolderId() === $pendingMove['folderId']
			&& $targetStorage->appliesSubfolderQuotas()
			&& $this->isDirectChildOf($target, $targetStorage->getFolder());
		if (!$remainsDirectChild) {
			$this->subfolderQuotaManager->removeSubfolderQuota($pendingMove['folderId'], $pendingMove['fileId']);
		}
	}

	private function removeQuotaAfterDeletion(NodeDeletedEvent $event): void {
		$node = $event->getNode();
		$storage = $node->getStorage();
		if (!$storage->instanceOfStorage(GroupFolderStorage::class)) {
			return;
		}

		/** @var GroupFolderStorage $storage */
		$this->subfolderQuotaManager->removeSubfolderQuota($storage->getFolderId(), $node->getId());
	}

	private function isDirectChildOf(Node $node, FolderDefinition $folder): bool {
		try {
			return $node->getParent()->getId() === $folder->rootId;
		} catch (NotFoundException) {
			return false;
		}
	}

	private function getMoveKey(Node $source, Node $target): string {
		return $source->getPath() . "\0" . $target->getPath();
	}
}
