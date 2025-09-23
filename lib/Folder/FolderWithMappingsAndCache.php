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
class FolderWithMappingsAndCache extends FolderDefinitionWithMappings {
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
		array $groups,
		array $manage,
		public readonly ICacheEntry $rootCacheEntry,
	) {
		parent::__construct($id, $mountPoint, $quota, $acl, $storageId, $rootId, $options, $groups, $manage);
	}

	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public static function fromFolderWithMapping(FolderDefinitionWithMappings $folder, ICacheEntry $rootCacheEntry): FolderWithMappingsAndCache {
		return new FolderWithMappingsAndCache(
			$folder->id,
			$folder->mountPoint,
			$folder->quota,
			$folder->acl,
			$folder->storageId,
			$folder->rootId,
			$folder->options,
			$folder->groups,
			$folder->manage,
			$rootCacheEntry,
		);
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'mount_point' => $this->mountPoint,
			'quota' => $this->quota,
			'acl' => $this->acl,
			'storage_id' => $this->storageId,
			'root_id' => $this->rootId,
			'root_cache_entry' => $this->rootCacheEntry,
			'groups' => $this->groups,
			'manage' => $this->manage,
		];
	}
}
