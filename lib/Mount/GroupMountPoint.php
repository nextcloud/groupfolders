<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Storage;
use OCP\Files\Mount\IShareOwnerlessMount;
use OCP\Files\Mount\ISystemMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;

class GroupMountPoint extends MountPoint implements ISystemMountPoint, IShareOwnerlessMount {
	public function __construct(
		private int $folderId,
		IStorage $storage,
		string $mountpoint,
		?array $arguments = null,
		?IStorageFactory $loader = null,
		?array $mountOptions = null,
		?int $mountId = null,
	) {
		/** @var Storage $storage */
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
