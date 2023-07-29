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
		return $path === '' ? ($this->checkMask(Constants::PERMISSION_UPDATE) && parent::isUpdatable($path)) : parent::isUpdatable($path);
	}

	public function isCreatable($path) {
		return $path === '' ? ($this->checkMask(Constants::PERMISSION_CREATE) && parent::isCreatable($path)) : parent::isCreatable($path);
	}

	public function isDeletable($path) {
		return $path === '' ? ($this->checkMask(Constants::PERMISSION_DELETE) && parent::isDeletable($path)) : parent::isDeletable($path);
	}

	public function isSharable($path) {
		return $path === '' ? ($this->checkMask(Constants::PERMISSION_SHARE) && parent::isSharable($path)) : parent::isSharable($path);
	}

	public function getPermissions($path) {
		return $path === '' ? $this->storage->getPermissions($path) & $this->mask : $this->storage->getPermissions($path);
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
		$storage ??= $this;

		$sourceCache = parent::getCache($path, $storage);
		return new CacheRootPermissionsMask($sourceCache, $this->mask);
	}
}
