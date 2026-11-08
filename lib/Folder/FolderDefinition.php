<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

class FolderDefinition {
	public const MANAGED_BY_GROUPFOLDERS = 'groupfolders';
	public const MANAGED_BY_TEAMS = 'teams';

	/**
	 * @param array{separate-storage?: bool} $options
	 * @param string $managedBy Which component manages this folder: {@see self::MANAGED_BY_GROUPFOLDERS} for regular group folders, {@see self::MANAGED_BY_TEAMS} for team folders.
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
		public readonly string $managedBy = self::MANAGED_BY_GROUPFOLDERS,
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
		return $this->managedBy === self::MANAGED_BY_TEAMS;
	}
}
