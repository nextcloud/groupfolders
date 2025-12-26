<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\DAV\ACLPlugin;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<\OCP\EventDispatcher\Event>
 */
class ACLPluginListener implements IEventListener {
	public function __construct(
		private RuleManager $ruleManager,
		private IUserSession $userSession,
		private FolderManager $folderManager,
		private IEventDispatcher $eventDispatcher,
		private ACLManagerFactory $aclManagerFactory,
		private IL10N $l10n,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof SabrePluginAddEvent) {
			return;
		}
		$event->getServer()->addPlugin(new ACLPlugin(
			$this->ruleManager,
			$this->userSession,
			$this->folderManager,
			$this->eventDispatcher,
			$this->aclManagerFactory,
			$this->l10n
		));
	}
}
