<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Storage\Storage;
use OC\Files\Storage\Wrapper\Wrapper;
use OCA\GroupFolders\Folder\FolderDefinition;
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
	private readonly int $mask;
	private readonly FolderDefinition $folder;

	/**
	 * @param array{storage: Storage, mask: int, folder: FolderDefinition} $arguments
	 *
	 * $storage: The storage the permissions mask should be applied on
	 * $mask: The permission bits that should be kept, a combination of the \OCP\Constant::PERMISSION_ constants
	 */
	public function __construct(array $arguments) {
		parent::__construct($arguments);
		$this->mask = $arguments['mask'];
		$this->folder = $arguments['folder'];
	}

	private function checkMask(int $permissions): bool {
		return ($this->mask & $permissions) === $permissions;
	}

	#[\Override]
	public function isUpdatable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_UPDATE) and parent::isUpdatable($path);
		}

		return parent::isUpdatable($path);
	}

	#[\Override]
	public function isCreatable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_CREATE) and parent::isCreatable($path);
		}

		return parent::isCreatable($path);
	}

	#[\Override]
	public function isDeletable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_DELETE) and parent::isDeletable($path);
		}

		return parent::isDeletable($path);
	}

	#[\Override]
	public function isSharable(string $path): bool {
		if ($path === '') {
			return $this->checkMask(Constants::PERMISSION_SHARE) and parent::isSharable($path);
		}

		return parent::isSharable($path);
	}

	#[\Override]
	public function getPermissions(string $path): int {
		if ($path === '') {
			return $this->getWrapperStorage()->getPermissions($path) & $this->mask;
		}

		return $this->getWrapperStorage()->getPermissions($path);
	}

	/**
	 * @return ?array<string, mixed>
	 */
	#[\Override]
	public function getMetaData(string $path): ?array {
		/** @var ?array{permissions?: int, scan_permissions?: int} $data */
		$data = parent::getMetaData($path);

		if ($data !== null && $path === '' && isset($data['permissions'])) {
			$data['scan_permissions'] ??= $data['permissions'];
			$data['permissions'] &= $this->mask;
		}

		return $data;
	}

	#[\Override]
	public function getCache(string $path = '', ?IStorage $storage = null): ICache {
		if (!$storage) {
			$storage = $this;
		}

		$sourceCache = parent::getCache($path, $storage);

		return new CacheRootPermissionsMask($sourceCache, $this->mask, $this->folder->rootId);
	}
}
