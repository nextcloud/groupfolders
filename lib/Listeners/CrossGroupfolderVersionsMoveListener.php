<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\Listeners;

use Exception;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\AbstractNodesEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use OC\Files\Node\NonExistingFile;

/**
 * @template-implements IEventListener<Event>
 */
class CrossGroupfolderVersionsMoveListener implements IEventListener {
	/** @var File[] */
	private array $movedFiles = [];

	public function __construct(
		private IUserSession $userSession,
		private VersionsBackend $versionsBackend,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof AbstractNodesEvent)) {
			return;
		}

		$source = $event->getSource();
		$target = $event->getTarget();

		$sourceStorage = $this->getNodeStorage($source);
		$targetStorage = $this->getNodeStorage($target);

		if (
			!$sourceStorage->instanceOfStorage(GroupFolderStorage::class) ||
			!$targetStorage->instanceOfStorage(GroupFolderStorage::class)
		) {
			return;
		}

		$currentUser = $this->userSession->getUser();

		if ($currentUser === null) {
			throw new Exception('Moving versions requires a user to be logged in');
		}

		if ($event instanceof BeforeNodeRenamedEvent) {
			$this->recursivelyPrepareMove($source);
		} elseif ($event instanceof NodeRenamedEvent || $event instanceof NodeCopiedEvent) {
			$this->recursivelyHandleMoveOrCopy($event, $source, $target);
		}
	}

	/**
	 * Store all sub files in $this->movedFiles so their info can be used after the operation.
	 */
	private function recursivelyPrepareMove(Node $source): void {
		if ($source instanceof File) {
			$this->movedFiles[$source->getId()] = $source;
		} elseif ($source instanceof Folder) {
			foreach ($source->getDirectoryListing() as $child) {
				$this->recursivelyPrepareMove($child);
			}
		}
	}

	/**
	 * Call handleMoveOrCopy on each sub files
	 * @param NodeRenamedEvent|NodeCopiedEvent $event
	 */
	private function recursivelyHandleMoveOrCopy(Event $event, ?Node $source, Node $target): void {
		if ($target instanceof File) {
			if ($event instanceof NodeRenamedEvent) {
				$source = $this->movedFiles[$target->getId()];
			}

			/** @var File $source */
			$this->handleMoveOrCopy($event, $source, $target);
		} elseif ($target instanceof Folder) {
			/** @var Folder $source */
			foreach ($target->getDirectoryListing() as $targetChild) {
				if ($event instanceof NodeCopiedEvent) {
					$sourceChild = $source->get($targetChild->getName());
				} else {
					$sourceChild = null;
				}

				$this->recursivelyHandleMoveOrCopy($event, $sourceChild, $targetChild);
			}
		}
	}

	/**
	 * Called only during NodeRenamedEvent or NodeCopiedEvent
	 * Will send the source node versions to the new backend, and then delete them from the old backend.
	 * @param NodeRenamedEvent|NodeCopiedEvent $event
	 */
	private function handleMoveOrCopy(Event $event, File $source, File $target): void {
		$sourceVersionsFolder = $this->versionsBackend->getVersionFolderForFile($source);
		$targetVersionsFolder = $this->versionsBackend->getVersionFolderForFile($target);

		foreach ($sourceVersionsFolder->getDirectoryListing() as $version) {
			if ($event instanceof NodeRenamedEvent) {
				$version->move($targetVersionsFolder->getInternalPath() . '/' . $version->getName());
			} else {
				$version->copy($targetVersionsFolder->getInternalPath() . '/' . $version->getName());
			}
		}
	}

	private function getNodeStorage(Node $node): IStorage {
		if ($node instanceof NonExistingFile) { // TODO: Fix
			return $node->getParent()->getStorage();
		} else {
			return $node->getStorage();
		}
	}
}
