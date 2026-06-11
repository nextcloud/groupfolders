<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

/**
 * @template-implements IEventListener<GroupDeletedEvent|UserDeletedEvent>
 */
class DeleteListener implements IEventListener {
	public function __construct(
		private readonly FolderManager $folderManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof GroupDeletedEvent) {
			$this->folderManager->deleteGroup($event->getGroup()->getGID());
		}
		if ($event instanceof UserDeletedEvent) {
			$this->folderManager->deleteUser($event->getUser()->getUID());
		}
	}
}
