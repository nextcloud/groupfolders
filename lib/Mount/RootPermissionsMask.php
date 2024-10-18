<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\Storage\IStorage;

/**
 * Permissions mask that only masks the root of the storage
 */
class RootPermissionsMask extends Wrapper {
	/**
	 * the permissions bits we want to keep
	 */
	private int $mask;

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

	private function checkMask(int $permissions): bool {
		return ($this->mask & $permissions) === $permissions;
	}

	public function isUpdatable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_UPDATE) and parent::isUpdatable($path);
		} else {
			return parent::isUpdatable($path);
		}
	}

	public function isCreatable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_CREATE) and parent::isCreatable($path);
		} else {
			return parent::isCreatable($path);
		}
	}

	public function isDeletable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_DELETE) and parent::isDeletable($path);
		} else {
			return parent::isDeletable($path);
		}
	}

	public function isSharable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_SHARE) and parent::isSharable($path);
		} else {
			return parent::isSharable($path);
		}
	}

	public function getPermissions(string $path): int {
		if ($path === '') {
			return $this->storage->getPermissions($path) & $this->mask;
		} else {
			return $this->storage->getPermissions($path);
		}
	}

	public function getMetaData(string $path): ?array {
		$data = parent::getMetaData($path);

		if ($data && $path === '' && isset($data['permissions'])) {
			$data['scan_permissions'] ??= $data['permissions'];
			$data['permissions'] &= $this->mask;
		}

		return $data;
	}

	public function getCache(string $path = '', ?IStorage $storage = null): ICache {
		if (!$storage) {
			$storage = $this;
		}

		$sourceCache = parent::getCache($path, $storage);

		return new CacheRootPermissionsMask($sourceCache, $this->mask);
	}
}
