<?php
/**
 * SPDX-FileCopyrightText: 2017 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Mount\MountPoint;

class GroupMountPoint extends MountPoint {
	/** @var int */
	private $folderId;

	public function __construct($folderId, $storage, $mountpoint, $arguments = null, $loader = null, $mountOptions = null, $mountId = null) {
		$this->folderId = $folderId;
		parent::__construct($storage, $mountpoint, $arguments, $loader, $mountOptions, $mountId, MountProvider::class);
	}

	public function getMountType() {
		return 'group';
	}

	public function getOption($name, $default) {
		$options = $this->getOptions();
		return isset($options[$name]) ? $options[$name] : $default;
	}

	public function getOptions() {
		$options = parent::getOptions();
		$options['encrypt'] = false;
		return $options;
	}

	public function getFolderId(): int {
		return $this->folderId;
	}

	public function getSourcePath(): string {
		return '/__groupfolders/' . $this->getFolderId();
	}
}
