<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use Icewind\Streams\IteratorDirectory;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IScanner;
use OCP\Files\Storage\IConstructableStorage;
use OCP\Files\Storage\IStorage;

class ACLStorageWrapper extends Wrapper implements IConstructableStorage {
	private ACLManager $aclManager;
	private bool $inShare;

	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->aclManager = $arguments['acl_manager'];
		$this->inShare = $arguments['in_share'];
	}

	private function getACLPermissionsForPath(string $path): int {
		$permissions = $this->aclManager->getACLPermissionsForPath($path);

		// if there is no read permissions, than deny everything
		if ($this->inShare) {
			$canRead = $permissions & (Constants::PERMISSION_READ + Constants::PERMISSION_SHARE);
		} else {
			$canRead = $permissions & Constants::PERMISSION_READ;
		}

		return $canRead ? $permissions : 0;
	}

	private function checkPermissions(string $path, int $permissions): bool {
		return ($this->getACLPermissionsForPath($path) & $permissions) === $permissions;
	}

	public function isReadable(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) && parent::isReadable($path);
	}

	public function isUpdatable(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_UPDATE) && parent::isUpdatable($path);
	}

	public function isCreatable(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_CREATE) && parent::isCreatable($path);
	}

	public function isDeletable(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE)
			&& $this->canDeleteTree($path)
			&& parent::isDeletable($path);
	}

	public function isSharable(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_SHARE) && parent::isSharable($path);
	}

	public function getPermissions(string $path): int {
		return $this->storage->getPermissions($path) & $this->getACLPermissionsForPath($path);
	}

	public function rename(string $source, string $target): bool {
		if (str_starts_with($source, $target)) {
			$part = substr($source, strlen($target));
			//This is a rename of the transfer file to the original file
			if (str_starts_with($part, '.ocTransferId')) {
				return $this->checkPermissions($target, Constants::PERMISSION_CREATE) && parent::rename($source, $target);
			}
		}

		$permissions = $this->file_exists($target) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		$sourceParent = dirname($source);
		if ($sourceParent === '.') {
			$sourceParent = '';
		}

		$targetParent = dirname($target);
		if ($targetParent === '.') {
			$targetParent = '';
		}

		return  ($sourceParent === $targetParent ||
			$this->checkPermissions($sourceParent, Constants::PERMISSION_DELETE)) &&
			$this->checkPermissions($source, Constants::PERMISSION_UPDATE & Constants::PERMISSION_READ) &&
			$this->checkPermissions($target, $permissions) &&
			parent::rename($source, $target);
	}

	public function opendir(string $path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		$handle = parent::opendir($path);
		if ($handle === false) {
			return false;
		}

		$items = [];
		while (($file = readdir($handle)) !== false) {
			if ($file !== '.' && $file !== '..') {
				if ($this->checkPermissions(trim($path . '/' . $file, '/'), Constants::PERMISSION_READ)) {
					$items[] = $file;
				}
			}
		}

		return IteratorDirectory::wrap($items);
	}

	public function copy(string $source, string $target): bool {
		$permissions = $this->file_exists($target) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($target, $permissions) &&
			$this->checkPermissions($source, Constants::PERMISSION_READ) &&
			parent::copy($source, $target);
	}

	public function touch(string $path, ?int $mtime = null): bool {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path, $permissions) && parent::touch($path, $mtime);
	}

	public function mkdir(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_CREATE) && parent::mkdir($path);
	}

	public function rmdir(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE)
			&& $this->canDeleteTree($path)
			&& parent::rmdir($path);
	}

	public function unlink(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE)
			&& $this->canDeleteTree($path)
			&& parent::unlink($path);
	}

	/**
	 * When deleting we need to ensure that there is no file inside the folder being deleted that misses delete permissions
	 * This check is fairly expensive so we only do it for the actual delete and not metadata operations
	 */
	private function canDeleteTree(string $path): int {
		return $this->aclManager->getPermissionsForTree($path) & Constants::PERMISSION_DELETE;
	}

	public function file_put_contents(string $path, mixed $data): int|float|false {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path, $permissions) ? parent::file_put_contents($path, $data) : false;
	}

	public function fopen(string $path, string $mode) {
		if ($mode === 'r' or $mode === 'rb') {
			$permissions = Constants::PERMISSION_READ;
		} else {
			$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		}

		return $this->checkPermissions($path, $permissions) ? parent::fopen($path, $mode) : false;
	}

	public function writeStream(string $path, $stream, ?int $size = null): int {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path, $permissions) ? parent::writeStream($path, $stream, $size) : 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getCache(string $path = '', ?IStorage $storage = null): ICache {
		if (!$storage) {
			$storage = $this;
		}

		$sourceCache = parent::getCache($path, $storage);

		return new ACLCacheWrapper($sourceCache, $this->aclManager, $this->inShare);
	}

	public function getMetaData(string $path): ?array {
		$data = parent::getMetaData($path);

		if ($data && isset($data['permissions'])) {
			$data['scan_permissions'] ??= $data['permissions'];
			$data['permissions'] &= $this->getACLPermissionsForPath($path);
		}

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	public function getScanner(string $path = '', ?IStorage $storage = null): IScanner {
		if (!$storage) {
			$storage = $this->storage;
		}

		return parent::getScanner($path, $storage);
	}

	public function is_dir(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::is_dir($path);
	}

	public function is_file(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::is_file($path);
	}

	public function stat(string $path): array|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::stat($path);
	}

	public function filetype(string $path): string|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::filetype($path);
	}

	public function filesize(string $path): false|int|float {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::filesize($path);
	}

	public function file_exists(string $path): bool {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::file_exists($path);
	}

	public function filemtime(string $path): int|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::filemtime($path);
	}

	public function file_get_contents(string $path): string|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::file_get_contents($path);
	}

	public function getMimeType(string $path): string|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::getMimeType($path);
	}

	public function hash(string $type, string $path, bool $raw = false): string|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::hash($type, $path, $raw);
	}

	public function getETag(string $path): string|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::getETag($path);
	}

	public function getDirectDownload(string $path): array|false {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		return parent::getDirectDownload($path);
	}

	public function getDirectoryContent(string $directory): \Traversable {
		$content = $this->getWrapperStorage()->getDirectoryContent($directory);
		foreach ($content as $data) {
			$data['scan_permissions'] ??= $data['permissions'];
			$data['permissions'] &= $this->getACLPermissionsForPath(rtrim($directory, '/') . '/' . $data['name']);

			yield $data;
		}
	}
}
