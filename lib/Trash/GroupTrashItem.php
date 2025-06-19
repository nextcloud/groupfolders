<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\TrashItem;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Files\FileInfo;
use OCP\IUser;

/**
 * @psalm-import-type InternalFolder from FolderManager
 */
class GroupTrashItem extends TrashItem {
	/**
	 * @param InternalFolder $folder
	 */
	public function __construct(
		ITrashBackend $backend,
		private readonly string $internalOriginalLocation,
		int $deletedTime,
		string $trashPath,
		FileInfo $fileInfo,
		IUser $user,
		private readonly string $mountPoint,
		?IUser $deletedBy,
		public readonly array $folder,
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

		return rtrim($path, '.d' . $this->getDeletedTime());
	}

	public function getGroupFolderStorageId(): int {
		return $this->folder['storage_id'];
	}
}
