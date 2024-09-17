<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders;

use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\CacheInsertEvent;
use OCP\Files\Cache\CacheUpdateEvent;
use OCP\Files\Cache\ICacheEvent;

class CacheListener {
	public function __construct(private IEventDispatcher $eventDispatcher) {
	}

	public function listen(): void {
		$this->eventDispatcher->addListener(CacheInsertEvent::class, [$this, 'onCacheEvent'], 99999);
		$this->eventDispatcher->addListener(CacheUpdateEvent::class, [$this, 'onCacheEvent'], 99999);
	}

	public function onCacheEvent(ICacheEvent $event): void {
		if (!$event->getStorage()->instanceOfStorage(GroupFolderStorage::class)) {
			return;
		}

		$jailedPath = preg_replace('/^__groupfolders\/\d+\//', '', $event->getPath());
		if ($jailedPath !== $event->getPath()) {
			$event->setPath($jailedPath);
		}
	}
}
