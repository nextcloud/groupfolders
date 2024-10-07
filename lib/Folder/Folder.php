<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OC\Files\Cache\CacheEntry;

class Folder {
	public function __construct(
		public int $folderId,
		public string $mountPoint,
		public int $permissions,
		public int $quota,
		public bool $acl,
		public ?CacheEntry $rootCacheEntry,
	) {
	}
}
