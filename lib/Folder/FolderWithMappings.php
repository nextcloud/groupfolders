<?php

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
class FolderWithMappings extends Folder {
	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public function __construct(
		int $id,
		string $mountPoint,
		int $permissions,
		int $quota,
		bool $acl,
		int $storageId,
		int $rootId,
		ICacheEntry $rootCacheEntry,
		public readonly array $groups,
		public readonly array $manage,
	) {
		parent::__construct($id, $mountPoint, $permissions, $quota, $acl, $storageId, $rootId, $rootCacheEntry);
	}

	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public static function fromFolder(Folder $folder, array $groups, array $manage): FolderWithMappings {
		return new FolderWithMappings(
			$folder->id,
			$folder->mountPoint,
			$folder->permissions,
			$folder->quota,
			$folder->acl,
			$folder->storageId,
			$folder->rootId,
			$folder->rootCacheEntry,
			$groups,
			$manage,
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
			'groups' => $this->groups,
			'manage' => $this->manage,
		];
	}

	public function withAddedPermissions(int $permissions): self {
		return new FolderWithMappings(
			$this->id,
			$this->mountPoint,
			$this->permissions | $permissions,
			$this->quota,
			$this->acl,
			$this->storageId,
			$this->rootId,
			$this->rootCacheEntry,
			$this->groups,
			$this->manage,
		);
	}
}
