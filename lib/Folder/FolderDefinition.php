<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

class FolderDefinition {
	/**
	 * @param array{separate-storage?: bool} $options
	 * @param ?string $teamCircleId The circle single id this team folder belongs to; null for regular group folders.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $mountPoint,
		public readonly int $quota,
		public readonly bool $acl,
		public readonly bool $aclDefaultNoPermission,
		public readonly int $storageId,
		public readonly int $rootId,
		public readonly array $options,
		public readonly ?string $teamCircleId = null,
	) {
	}

	public function useSeparateStorage(): bool {
		return $this->options['separate-storage'] ?? false;
	}

	/**
	 * Whether this folder belongs to a team (i.e. is a team folder) and
	 * therefore cannot be deleted or renamed independently of that team.
	 */
	public function isTeamSpace(): bool {
		return $this->teamCircleId !== null;
	}
}
