<?php
/**
 * @copyright Copyright (c) 2022 Carl Schwan <carl@carlschwan.eu>
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

use OC\Files\Storage\Wrapper\Wrapper;
use OCA\GroupFolders\ACL\ACLCacheWrapper;
use OCP\Files\Cache\ICacheEntry;

/**
 * Storage wrapper allowing to disable sharing and other permissions for the
 * root of the group folders.
 */
class MountPermissionStorageWrapper extends Wrapper {

	private ICacheEntry $rootEntry;

	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->rootEntry = $parameters['rootCacheEntry'];
	}

	public function isSharable($path) {
		return $this->rootEntry->getPath() . '/' . $this->rootEntry['mount_point'] !== $path && parent::isSharable($path);
	}

	/**
	 * get a cache instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the cache
	 * @return \OCP\Files\Cache\ICache
	 */
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		$sourceCache = parent::getCache($path, $storage);
		return new MountPermissionCacheWrapper($sourceCache, $this->rootEntry);
	}
}
