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
use OC\Files\Storage\Wrapper\Quota;
use OCP\Files\Cache\ICacheEntry;
use OCP\IUser;
use OCP\IUserSession;

class GroupFolderStorage extends Quota {
	/** @var int */
	private $folderId;

	/** @var ICacheEntry */
	private $rootEntry;

	/** @var IUserSession */
	private $userSession;
	/** @var IUser */
	private $mountOwner;

	public $cache;

	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->folderId = $parameters['folder_id'];
		$this->rootEntry = $parameters['rootCacheEntry'];
		$this->userSession = $parameters['userSession'];
		$this->mountOwner = $parameters['mountOwner'];
	}

	public function getFolderId() {
		return $this->folderId;
	}

	public function getOwner($path) {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $user->getUID();
		}
		return $this->mountOwner !== null ? $this->mountOwner->getUID() : false;
	}

	public function instanceOfStorage($class) {
		// "implement" the interface without adding a hard dependency on nc15
		if ($class === 'OCP\Files\Storage\IDisableEncryptionStorage') {
			return true;
		}
		return parent::instanceOfStorage($class);
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
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($storage->scanner)) {
			$storage->scanner = new Scanner($storage);
		}
		return $storage->scanner;
	}
}
