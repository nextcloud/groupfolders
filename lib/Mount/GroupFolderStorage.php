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
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IScanner;
use OCP\Files\Storage\IConstructableStorage;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\IUserSession;

class GroupFolderStorage extends Quota implements IConstructableStorage {
	private int $folderId;
	private ICacheEntry $rootEntry;
	private IUserSession $userSession;
	private ?IUser $mountOwner;
	/** @var RootEntryCache|null */
	public $cache;

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

	public function getOwner($path): string|false {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $user->getUID();
		}

		return $this->mountOwner !== null ? $this->mountOwner->getUID() : false;
	}

	/**
	 * @inheritDoc
	 * @param string $path
	 * @param ?IStorage $storage
	 */
	public function getCache($path = '', $storage = null): ICache {
		if ($this->cache) {
			return $this->cache;
		}

		if (!$storage) {
			$storage = $this;
		}

		$this->cache = new RootEntryCache(parent::getCache($path, $storage), $this->rootEntry);

		return $this->cache;
	}

	/**
	 * @inheritDoc
	 * @param string $path
	 * @param ?IStorage $storage
	 */
	public function getScanner($path = '', $storage = null): IScanner {
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

	public function hasUpdated($path, $time) {
		return false;
	}
}
