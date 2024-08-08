<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
