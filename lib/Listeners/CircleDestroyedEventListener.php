<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<CircleDestroyedEvent>
 */
class CircleDestroyedEventListener implements IEventListener {
	public function __construct(
		private readonly FolderManager $folderManager,
	) {
	}


	#[\Override]
	public function handle(Event $event): void {
		/** @phpstan-ignore instanceof.alwaysTrue */
		if (!$event instanceof CircleDestroyedEvent) {
			return;
		}

		$circle = $event->getCircle();
		$this->folderManager->deleteCircle($circle->getSingleId());
	}
}
