<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Mount\MountPoint;
use OCP\Files\Mount\ISystemMountPoint;

class GroupMountPoint extends MountPoint implements ISystemMountPoint {
	/** @var int */
	private $folderId;

	public function __construct($folderId, $storage, $mountpoint, $arguments = null, $loader = null, $mountOptions = null, $mountId = null) {
		$this->folderId = $folderId;
		parent::__construct($storage, $mountpoint, $arguments, $loader, $mountOptions, $mountId, MountProvider::class);
	}

	public function getMountType() {
		return 'group';
	}

	public function getFolderId(): int {
		return $this->folderId;
	}

	public function getSourcePath(): string {
		return '/__groupfolders/' . $this->getFolderId();
	}
}
