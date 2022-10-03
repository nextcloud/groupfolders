<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Robin Appelman <robin@icewind.nl>
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

use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;

/**
 * Permissions mask that only masks the root of the storage
 */
class RootPermissionsMask extends Wrapper {
	/**
	 * @var int the permissions bits we want to keep
	 */
	private $mask;

	/**
	 * @param array $arguments ['storage' => $storage, 'mask' => $mask]
	 *
	 * $storage: The storage the permissions mask should be applied on
	 * $mask: The permission bits that should be kept, a combination of the \OCP\Constant::PERMISSION_ constants
	 */
	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->mask = $arguments['mask'];
	}

	private function checkMask($permissions) {
		return ($this->mask & $permissions) === $permissions;
	}

	public function isUpdatable($path) {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_UPDATE) and parent::isUpdatable($path);
		} else {
			return parent::isUpdatable($path);
		}
	}

	public function isCreatable($path) {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_CREATE) and parent::isCreatable($path);
		} else {
			return parent::isCreatable($path);
		}
	}

	public function isDeletable($path) {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_DELETE) and parent::isDeletable($path);
		} else {
			return parent::isDeletable($path);
		}
	}

	public function isSharable($path) {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_SHARE) and parent::isSharable($path);
		} else {
			return parent::isSharable($path);
		}
	}

	public function getPermissions($path) {
		if ($path === '') {
			return $this->storage->getPermissions($path) & $this->mask;
		} else {
			return $this->storage->getPermissions($path);
		}
	}

	public function getMetaData($path) {
		$data = parent::getMetaData($path);

		if ($data && $path === '' && isset($data['permissions'])) {
			$data['scan_permissions'] = $data['scan_permissions'] ?? $data['permissions'];
			$data['permissions'] &= $this->mask;
		}
		return $data;
	}

	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		$sourceCache = parent::getCache($path, $storage);
		return new CacheRootPermissionsMask($sourceCache, $this->mask);
	}
}
