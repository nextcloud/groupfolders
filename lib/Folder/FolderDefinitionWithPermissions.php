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
 * @phpstan-import-type GroupFoldersApplicable from ResponseDefinitions
 * @phpstan-import-type GroupFoldersAclManage from ResponseDefinitions
 */
class FolderDefinitionWithPermissions extends FolderDefinition {
	public function __construct(
		int $id,
		string $mountPoint,
		int $quota,
		bool $acl,
		bool $aclDefaultNoPermission,
		int $storageId,
		int $rootId,
		array $options,
		public readonly ICacheEntry $rootCacheEntry,
		public readonly int $permissions,
	) {
		parent::__construct($id, $mountPoint, $quota, $acl, $aclDefaultNoPermission, $storageId, $rootId, $options);
	}

	public static function fromFolder(FolderDefinition $folder, ICacheEntry $rootCacheEntry, int $permissions): FolderDefinitionWithPermissions {
		return new FolderDefinitionWithPermissions(
			$folder->id,
			$folder->mountPoint,
			$folder->quota,
			$folder->acl,
			$folder->aclDefaultNoPermission,
			$folder->storageId,
			$folder->rootId,
			$folder->options,
			$rootCacheEntry,
			$permissions,
		);
	}

	/**
	 * @return array{
	 *     id: int,
	 *     mount_point: string,
	 *     permissions: int,
	 *     quota: int,
	 *     acl: bool,
	 *     acl_default_no_permission: bool,
	 *     storage_id: int,
	 *     root_id: int,
	 *     root_cache_entry: ICacheEntry,
	 * }
	 */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'mount_point' => $this->mountPoint,
			'permissions' => $this->permissions,
			'quota' => $this->quota,
			'acl' => $this->acl,
			'acl_default_no_permission' => $this->aclDefaultNoPermission,
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
			$this->aclDefaultNoPermission,
			$this->storageId,
			$this->rootId,
			$this->options,
			$this->rootCacheEntry,
			$this->permissions | $permissions,
		);
	}
}
