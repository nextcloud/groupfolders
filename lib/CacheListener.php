<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders;

use OC\Files\Storage\Wrapper\Jail;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\CacheEntryInsertedEvent;
use OCP\Files\Cache\CacheEntryUpdatedEvent;
use OCP\Files\Cache\ICacheEvent;

class CacheListener {
	public function __construct(
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	public function listen(): void {
		$this->eventDispatcher->addListener(CacheEntryInsertedEvent::class, $this->onCacheEvent(...), 99999);
		$this->eventDispatcher->addListener(CacheEntryUpdatedEvent::class, $this->onCacheEvent(...), 99999);
	}

	public function onCacheEvent(ICacheEvent $event): void {
		$storage = $event->getStorage();
		if (!$storage->instanceOfStorage(GroupFolderStorage::class)) {
			return;
		}
		if (!$storage->instanceOfStorage(Jail::class)) {
			return;
		}
		if ($path = $storage->getJailedPath($event->getPath())) {
			$event->setPath($path);
		}
	}
}
