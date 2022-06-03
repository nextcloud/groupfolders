<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Robin Appelman <robin@icewind.nl>
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
use OCP\Files\Cache\ICache;

class CacheRootPermissionsMask extends CacheWrapper {
	protected int $mask;

	public function __construct(ICache $cache, int $mask) {
		parent::__construct($cache);
		$this->mask = $mask;
	}

	protected function formatCacheEntry($entry) {
		$path = $entry['path'];
		$isRoot = $path === '' || (strpos($path, '__groupfolders') === 0 && count(explode('/', $path)) === 2);
		if (isset($entry['permissions']) && $isRoot) {
			$entry['scan_permissions'] = $entry['permissions'];
			$entry['permissions'] &= $this->mask;
		}
		return $entry;
	}
}
