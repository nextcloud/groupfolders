<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\GroupFolders\DAV\PropFindPlugin;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<\OCP\EventDispatcher\Event>
 */
class PropFindPluginListener implements IEventListener {
	public function __construct(
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof SabrePluginAddEvent) {
			return;
		}
		$event->getServer()->addPlugin(new PropFindPlugin(
			$this->rootFolder,
			$this->userSession
		));
	}
}
