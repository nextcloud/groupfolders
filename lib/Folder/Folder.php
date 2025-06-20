<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCP\Files\Cache\ICacheEntry;

class Folder {
	public function __construct(
		public readonly int $id,
		public readonly string $mountPoint,
		public readonly int $permissions,
		public readonly int $quota,
		public readonly bool $acl,
		public readonly int $storageId,
		public readonly int $rootId,
		public readonly ICacheEntry $rootCacheEntry,
	) {
	}

	public function withAddedPermissions(int $permissions): self {
		return new Folder(
			$this->id,
			$this->mountPoint,
			$this->permissions | $permissions,
			$this->quota,
			$this->acl,
			$this->storageId,
			$this->rootId,
			$this->rootCacheEntry
		);
	}
}
