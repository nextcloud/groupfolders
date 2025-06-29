<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCA\GroupFolders\ResponseDefinitions;

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
		int $quota,
		bool $acl,
		int $storageId,
		int $rootId,
		public readonly array $groups,
		public readonly array $manage,
	) {
		parent::__construct($id, $mountPoint, $quota, $acl, $storageId, $rootId);
	}

	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public static function fromFolder(Folder $folder, array $groups, array $manage): FolderWithMappings {
		return new FolderWithMappings(
			$folder->id,
			$folder->mountPoint,
			$folder->quota,
			$folder->acl,
			$folder->storageId,
			$folder->rootId,
			$groups,
			$manage,
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
			'groups' => $this->groups,
			'manage' => $this->manage,
		];
	}
}
