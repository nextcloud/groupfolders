<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\TrashItem;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCP\Files\Node;
use OCP\IUser;

class GroupTrashItem extends TrashItem {
	public function __construct(
		ITrashBackend $backend,
		private readonly string $internalOriginalLocation,
		int $deletedTime,
		string $trashPath,
		protected Node $fileInfo,
		IUser $user,
		private readonly string $mountPoint,
		?IUser $deletedBy,
		public readonly FolderDefinitionWithPermissions $folder,
	) {
		parent::__construct($backend, $this->mountPoint . '/' . $this->internalOriginalLocation, $deletedTime, $trashPath, $fileInfo, $user, $deletedBy);
	}

	public function getInternalOriginalLocation(): string {
		return $this->internalOriginalLocation;
	}

	public function isRootItem(): bool {
		return substr_count($this->getTrashPath(), '/') === 2;
	}

	public function getGroupFolderMountPoint(): string {
		return $this->mountPoint;
	}

	public function getTitle(): string {
		return $this->getGroupFolderMountPoint() . '/' . $this->getOriginalLocation();
	}

	public function getMtime(): int {
		// trashbin is currently (incorrectly) assuming these to be the same
		return $this->getDeletedTime();
	}

	public function getInternalPath(): string {
		// trashbin expects the path without the deletion timestamp
		$path = parent::getInternalPath();
		$deletionExtension = '.d' . $this->getDeletedTime();

		if (str_ends_with($path, $deletionExtension)) {
			$path = substr($path, 0, -strlen($deletionExtension));
		}

		return $path;
	}

	public function getFullInternalPath(): string {
		return parent::getInternalPath();
	}

	public function getTrashNode(): Node {
		return $this->fileInfo;
	}

	public function getGroupTrashFolderStorageId(): int {
		return $this->fileInfo->getStorage()->getCache()->getNumericStorageId();
	}

	public function getGroupFolderStorageId(): int {
		return $this->folder->storageId;
	}
}
