<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\Mount;

use OC\Files\Mount\MountPoint;

class GroupMountPoint extends MountPoint {
	/** @var int */
	private $folderId;

	public function __construct($folderId, $storage, $mountpoint, $arguments = null, $loader = null, $mountOptions = null, $mountId = null) {
		$this->folderId = $folderId;
		parent::__construct($storage, $mountpoint, $arguments, $loader, $mountOptions, $mountId);
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
