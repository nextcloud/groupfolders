<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Scanner;
use OC\Files\ObjectStore\ObjectStoreScanner;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\Quota;
use OC\Files\Storage\Wrapper\Wrapper;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
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
		// note that we explicitly don't used the passed in storage
		// as we want to perform the scan on the underlying filesystem
		// without any of the group folder permissions applied

		/** @var Wrapper $storage */
		$storage = $this->storage;

		// we want to scan without ACLs applied
		if ($storage->instanceOfStorage(ACLStorageWrapper::class)) {
			// sanity check in case the code setting up the wrapper hierarchy is changed without updating this
			if (!$this->storage instanceof Jail) {
				throw new \Exception("groupfolder storage layout changed unexpectedly");
			}

			$jailRoot = $this->storage->getUnjailedPath('');
			$aclStorage = $this->storage->getUnjailedStorage();

			if (!$aclStorage instanceof ACLStorageWrapper) {
				throw new \Exception("groupfolder storage layout changed unexpectedly");
			}
			$storage = new Jail([
				'storage' => $aclStorage->getWrapperStorage(),
				'root' => $jailRoot,
			]);
		}

		if ($storage->instanceOfStorage(ObjectStoreStorage::class)) {
			$storage->scanner = new ObjectStoreScanner($storage);
		} elseif (!isset($storage->scanner)) {
			$storage->scanner = new Scanner($storage);
		}
		return $storage->scanner;
	}
}
