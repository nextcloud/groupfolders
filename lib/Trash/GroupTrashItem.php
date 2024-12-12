<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\Trash;

use OC\Files\Storage\Wrapper\Jail;
use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\TrashItem;
use OCP\Files\FileInfo;
use OCP\IUser;

class GroupTrashItem extends TrashItem {
	private string $internalOriginalLocation;

	public function __construct(
		ITrashBackend $backend,
		string $originalLocation,
		int $deletedTime,
		string $trashPath,
		FileInfo $fileInfo,
		IUser $user,
		private string $mountPoint
	) {
		$this->internalOriginalLocation = $originalLocation;
		parent::__construct($backend, $this->mountPoint . '/' . $originalLocation, $deletedTime, $trashPath, $fileInfo, $user);
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

	public function getInternalPath() {
		// trashbin expects the path without the deletion timestamp
		$path = parent::getInternalPath();
		return rtrim($path, '.d' . $this->getDeletedTime());
	}
}
