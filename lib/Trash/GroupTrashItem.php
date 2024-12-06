<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\TrashItem;
use OCP\Files\FileInfo;
use OCP\IUser;

class GroupTrashItem extends TrashItem {
	public function __construct(
		ITrashBackend $backend,
		private string $internalOriginalLocation,
		int $deletedTime,
		string $trashPath,
		FileInfo $fileInfo,
		IUser $user,
		private string $mountPoint,
		?IUser $deletedBy,
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
}
