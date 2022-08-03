<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2020 Julius Härtl <jus@bitgrid.net>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\GroupFolders\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class LoadAdditionalScriptsListener implements IEventListener {
	public function handle(Event $event): void {
		\OCP\Util::addScript('groupfolders', 'groupfolders-files');
	}
}
