<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Scanner;
use OC\Files\ObjectStore\ObjectStoreScanner;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Quota;
use OCP\Files\Cache\ICacheEntry;
use OCP\IUser;
use OCP\IUserSession;

class GroupFolderStorage extends Quota {
	private int $folderId;
	private ICacheEntry $rootEntry;
	private IUserSession $userSession;
	private ?IUser $mountOwner = null;
	/** @var RootEntryCache|null */
	public $cache = null;

	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->folderId = $parameters['folder_id'];
		$this->rootEntry = $parameters['rootCacheEntry'];
		$this->userSession = $parameters['userSession'];
		$this->mountOwner = $parameters['mountOwner'];
	}

	public function getFolderId(): int {
		return $this->folderId;
	}

	/**
	 * @psalm-suppress FalsableReturnStatement Return type of getOwner is not clear even in server
	 */
	public function getOwner($path) {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $user->getUID();
		}
		return $this->mountOwner !== null ? $this->mountOwner->getUID() : false;
	}

	public function getCache($path = '', $storage = null) {
		if ($this->cache) {
			return $this->cache;
		}
		if (!$storage) {
			$storage = $this;
		}

		$this->cache = new RootEntryCache(parent::getCache($path, $storage), $this->rootEntry);
		return $this->cache;
	}

	public function getScanner($path = '', $storage = null) {
		/** @var \OC\Files\Storage\Wrapper\Wrapper $storage */
		if (!$storage) {
			$storage = $this;
		}
		if ($storage->instanceOfStorage(ObjectStoreStorage::class)) {
			$storage->scanner = new ObjectStoreScanner($storage);
		} elseif (!isset($storage->scanner)) {
			$storage->scanner = new Scanner($storage);
		}
		return $storage->scanner;
	}

	protected function shouldApplyQuota(string $path): bool {
		return true;
	}
}
