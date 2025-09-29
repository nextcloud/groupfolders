<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Wrapper\CacheWrapper;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;

class CacheRootPermissionsMask extends CacheWrapper {
	public function __construct(
		ICache $cache,
		private readonly int $mask,
		private readonly int $rootId,
	) {
		parent::__construct($cache);
	}

	protected function formatCacheEntry($entry): ICacheEntry|false {
		$isRoot = $entry->getId() === $this->rootId;
		if (isset($entry['permissions']) && $isRoot) {
			$entry['scan_permissions'] ??= $entry['permissions'];
			$entry['permissions'] &= $this->mask;
		}

		return $entry;
	}
}
