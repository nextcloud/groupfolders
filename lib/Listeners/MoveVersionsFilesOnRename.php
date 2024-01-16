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

use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Trash\TrashManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<NodeRenamedEvent>
 */
class MoveVersionsFilesOnRename implements IEventListener {
	public function __construct(
		private TrashManager $trashManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		$source = $event->getSource();
		$target = $event->getTarget();
		// Look at the parent because the node itself is not existing anymore
		/** @var GroupFolderStorage */
		$sourceStorage = $source->getParent()->getStorage();
		/** @var GroupFolderStorage */
		$targetStorage = $target->getStorage();

		if (($target instanceof File) &&
			$sourceStorage !== $targetStorage &&
			$sourceStorage->instanceOfStorage(GroupFolderStorage::class) &&
			$targetStorage->instanceOfStorage(GroupFolderStorage::class)) {
			$sourceFolderId = $sourceStorage->getFolderId();
			$targetFolderId = $targetStorage->getFolderId();
		}
	}
}
