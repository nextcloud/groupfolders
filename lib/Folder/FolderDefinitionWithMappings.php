<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCA\GroupFolders\ResponseDefinitions;

/**
 * @phpstan-import-type GroupFoldersApplicable from ResponseDefinitions
 * @phpstan-import-type GroupFoldersAclManage from ResponseDefinitions
 */
class FolderDefinitionWithMappings extends FolderDefinition {
	/**
	 * @param array<string, GroupFoldersApplicable> $groups
	 * @param list<GroupFoldersAclManage> $manage
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
	 * @param array<string, GroupFoldersApplicable> $groups
	 * @param list<GroupFoldersAclManage> $manage
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

	/**
	 * @return array{
	 *     id: int,
	 *     mount_point: string,
	 *     quota: int,
	 *     acl: bool,
	 *     acl_default_no_permission: bool,
	 *     storage_id: int,
	 *     root_id: int,
	 *     groups: array<string, GroupFoldersApplicable>,
	 *     manage: list<GroupFoldersAclManage>,
	 * }
	 */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'mount_point' => $this->mountPoint,
			'quota' => $this->quota,
			'acl' => $this->acl,
			'acl_default_no_permission' => $this->aclDefaultNoPermission,
			'storage_id' => $this->storageId,
			'root_id' => $this->rootId,
			'groups' => $this->groups,
			'manage' => $this->manage,
		];
	}
}
