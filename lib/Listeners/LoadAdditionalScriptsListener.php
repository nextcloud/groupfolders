<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\GroupFolders\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent|BeforeTemplateRenderedEvent>
 */
class LoadAdditionalScriptsListener implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		/** @phpstan-ignore instanceof.alwaysTrue, booleanAnd.alwaysFalse */
		if (!$event instanceof LoadAdditionalScriptsEvent && !$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		Util::addInitScript(Application::APP_ID, Application::APP_ID . '-initFiles');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-initFiles');
	}
}
