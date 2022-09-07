<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders;

use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCP\Files\Cache\CacheInsertEvent;
use OCP\Files\Cache\CacheUpdateEvent;
use OCP\Files\Cache\ICacheEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CacheListener {
	private EventDispatcher $eventDispatcher;

	public function __construct(EventDispatcher $eventDispatcher) {
		$this->eventDispatcher = $eventDispatcher;
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
