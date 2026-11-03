<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Config\ConfigLexicon;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Config\IUserConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Events\UserHomeSetupEvent;
use OCP\Files\Storage\IStorageFactory;

/**
 * Listen to the users filesystem setup being started, to perform any groupfolder mount
 * work that was postponed.
 *
 * @template-implements IEventListener<UserHomeSetupEvent>
 */
readonly class UserHomeSetupListener implements IEventListener {
	public function __construct(
		private IUserConfig $userConfig,
		private IUserMountCache $userMountCache,
		private MountProvider $mountProvider,
		private IStorageFactory $storageFactory,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof UserHomeSetupEvent) {
			return;
		}

		$user = $event->getUser();
		if ($this->userConfig->getValueBool($user->getUID(), Application::APP_ID, ConfigLexicon::USER_NEEDS_MOUNT_REFRESH, true)) {
			$mounts = $this->mountProvider->getMountsForUser($user, $this->storageFactory);
			$this->userMountCache->registerMounts($user, $mounts, [MountProvider::class]);
			$this->userConfig->setValueBool($user->getUID(), Application::APP_ID, ConfigLexicon::USER_NEEDS_MOUNT_REFRESH, false);
		}
	}
}
