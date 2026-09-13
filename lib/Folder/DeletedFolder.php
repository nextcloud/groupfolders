<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCP\Files\Cache\ICacheEntry;

/**
 * A Team folder that has been removed from users' mounts but is still within
 * its recovery period.
 */
class DeletedFolder {
	public function __construct(
		public readonly FolderDefinition $folder,
		public readonly ICacheEntry $rootCacheEntry,
		public readonly int $deletedAt,
		public readonly ?string $deletedBy,
	) {
	}
}
