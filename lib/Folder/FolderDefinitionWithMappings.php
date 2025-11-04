<?php

declare (strict_types=1);
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
class FolderDefinitionWithMappings extends FolderDefinition {
	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public function __construct(
		int $id,
		string $mountPoint,
		int $quota,
		bool $acl,
		bool $aclDefaultNoPermission,
		int $storageId,
		int $rootId,
		array $options,
		public readonly array $groups,
		public readonly array $manage,
	) {
		parent::__construct($id, $mountPoint, $quota, $acl, $aclDefaultNoPermission, $storageId, $rootId, $options);
	}

	/**
	 * @psalm-param array<string, GroupFoldersApplicable> $groups
	 * @psalm-param list<GroupFoldersAclManage> $manage
	 */
	public static function fromFolder(FolderDefinition $folder, array $groups, array $manage): FolderDefinitionWithMappings {
		return new FolderDefinitionWithMappings(
			$folder->id,
			$folder->mountPoint,
			$folder->quota,
			$folder->acl,
			$folder->aclDefaultNoPermission,
			$folder->storageId,
			$folder->rootId,
			$folder->options,
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
