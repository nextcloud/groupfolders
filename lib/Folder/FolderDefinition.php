<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

class FolderDefinition {
	public function __construct(
		public readonly int $id,
		public readonly string $mountPoint,
		public readonly int $quota,
		public readonly bool $acl,
		public readonly bool $aclDefaultNoPermission,
		public readonly int $storageId,
		public readonly int $rootId,
		public readonly array $options,
	) {
	}

	public function useSeparateStorage(): bool {
		return $this->options['separate-storage'] ?? false;
	}
}
