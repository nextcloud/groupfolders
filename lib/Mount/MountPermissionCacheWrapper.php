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

use OC\Files\Cache\Wrapper\CacheWrapper;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchQuery;

class MountPermissionCacheWrapper extends CacheWrapper {
	private ICacheEntry $rootEntry;

	private function getPermissionsForPath(string $path): int {
		return $path !== $this->rootEntry->getPath() . '/' . $this->rootEntry['mount_point'] ? Constants::PERMISSION_ALL : Constants::PERMISSION_READ;
	}

	public function __construct(ICache $cache, ICacheEntry $rootCacheEntry) {
		parent::__construct($cache);
		$this->rootEntry = $rootCacheEntry;
	}

	protected function formatCacheEntry($entry) {
		if (isset($entry['permissions'])) {
			$entry['scan_permissions'] = $entry['permissions'];
			$entry['permissions'] &= $this->getPermissionsForPath($entry['path']);
			if (!$entry['permissions']) {
				return false;
			}
		}
		return $entry;
	}

	public function getFolderContentsById($fileId) {
		$results = $this->getCache()->getFolderContentsById($fileId);
		$entries = array_map([$this, 'formatCacheEntry'], $results);
		return array_filter(array_filter($entries));
	}

	public function search($pattern) {
		$results = $this->getCache()->search($pattern);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	public function searchByMime($mimetype) {
		$results = $this->getCache()->searchByMime($mimetype);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	public function searchQuery(ISearchQuery $query) {
		$results = $this->getCache()->searchQuery($query);
		return array_map([$this, 'formatCacheEntry'], $results);
	}
}
