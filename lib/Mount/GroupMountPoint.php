<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Mount\MountPoint;
use OCP\Files\Mount\ISystemMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;

class GroupMountPoint extends MountPoint implements ISystemMountPoint {
	/**
	 * @param IStorage $storage
	 */
	public function __construct(
		private int $folderId,
		$storage,
		string $mountpoint,
		?array $arguments = null,
		?IStorageFactory $loader = null,
		?array $mountOptions = null,
		?int $mountId = null,
	) {
		parent::__construct($storage, $mountpoint, $arguments, $loader, $mountOptions, $mountId, MountProvider::class);
	}

	public function getMountType(): string {
		return 'group';
	}

	public function getFolderId(): int {
		return $this->folderId;
	}

	public function getSourcePath(): string {
		return '/__groupfolders/' . $this->getFolderId();
	}
}
