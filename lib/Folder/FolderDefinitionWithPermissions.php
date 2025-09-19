<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCA\GroupFolders\ResponseDefinitions;
use OCP\Files\Cache\ICacheEntry;

/**
 * @psalm-import-type GroupFoldersApplicable from ResponseDefinitions
 * @psalm-import-type GroupFoldersAclManage from ResponseDefinitions
 */
class FolderDefinitionWithPermissions extends FolderDefinition {
	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public function __construct(
		int $id,
		string $mountPoint,
		int $quota,
		bool $acl,
		int $storageId,
		int $rootId,
		array $options,
		public readonly ICacheEntry $rootCacheEntry,
		public readonly int $permissions,
	) {
		parent::__construct($id, $mountPoint, $quota, $acl, $storageId, $rootId, $options);
	}

	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public static function fromFolder(FolderDefinition $folder, ICacheEntry $rootCacheEntry, int $permissions): FolderDefinitionWithPermissions {
		return new FolderDefinitionWithPermissions(
			$folder->id,
			$folder->mountPoint,
			$folder->quota,
			$folder->acl,
			$folder->storageId,
			$folder->rootId,
			$folder->options,
			$rootCacheEntry,
			$permissions,
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'mount_point' => $this->mountPoint,
			'permissions' => $this->permissions,
			'quota' => $this->quota,
			'acl' => $this->acl,
			'storage_id' => $this->storageId,
			'root_id' => $this->rootId,
			'root_cache_entry' => $this->rootCacheEntry,
		];
	}

	public function withAddedPermissions(int $permissions): self {
		return new FolderDefinitionWithPermissions(
			$this->id,
			$this->mountPoint,
			$this->quota,
			$this->acl,
			$this->storageId,
			$this->rootId,
			$this->options,
			$this->rootCacheEntry,
			$this->permissions | $permissions,
		);
	}
}
