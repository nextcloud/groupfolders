<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OC\Files\Storage\Wrapper\Jail;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\CacheEntryInsertedEvent;
use OCP\Files\Cache\CacheEntryUpdatedEvent;

/**
 * @template-implements IEventListener<CacheEntryInsertedEvent|CacheEntryUpdatedEvent>
 */
class CacheListener implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		/** @phpstan-ignore instanceof.alwaysTrue, booleanAnd.alwaysFalse */
		if (!$event instanceof CacheEntryInsertedEvent && !$event instanceof CacheEntryUpdatedEvent) {
			return;
		}

		$storage = $event->getStorage();
		if (!$storage->instanceOfStorage(GroupFolderStorage::class)) {
			return;
		}
		/** @phpstan-ignore method.impossibleType */
		if (!$storage->instanceOfStorage(Jail::class)) {
			return;
		}

		/** @var Jail $storage @phpstan-ignore varTag.nativeType */
		$path = $storage->getJailedPath($event->getPath());
		if ($path !== null) {
			$event->setPath($path);
		}
	}
}
