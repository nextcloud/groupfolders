<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\ACL;

use Icewind\Streams\IteratorDirectory;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;

class ACLStorageWrapper extends Wrapper {
	/** @var ACLManager */
	private $aclManager;
	/** @var bool */
	private $inShare;

	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->aclManager = $arguments['acl_manager'];
		$this->inShare = $arguments['in_share'];
	}

	private function getACLPermissionsForPath(string $path) {
		$permissions = $this->aclManager->getACLPermissionsForPath($path);

		// if there is no read permissions, than deny everything
		if ($this->inShare) {
			$canRead = $permissions & (Constants::PERMISSION_READ + Constants::PERMISSION_SHARE);
		} else {
			$canRead = $permissions & Constants::PERMISSION_READ;
		}
		return $canRead ? $permissions : 0;
	}

	private function checkPermissions(string $path, int $permissions) {
		return ($this->getACLPermissionsForPath($path) & $permissions) === $permissions;
	}

	public function isReadable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) && parent::isReadable($path);
	}

	public function isUpdatable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_UPDATE) && parent::isUpdatable($path);
	}

	public function isCreatable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_CREATE) && parent::isCreatable($path);
	}

	public function isDeletable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE)
			&& $this->canDeleteTree($path)
			&& parent::isDeletable($path);
	}

	public function isSharable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_SHARE) && parent::isSharable($path);
	}

	public function getPermissions($path) {
		return $this->storage->getPermissions($path) & $this->getACLPermissionsForPath($path);
	}

	public function rename($path1, $path2) {
		if (strpos($path1, $path2) === 0) {
			$part = substr($path1, strlen($path2));
			//This is a rename of the transfer file to the original file
			if (strpos($part, '.ocTransferId') === 0) {
				return $this->checkPermissions($path2, Constants::PERMISSION_CREATE) && parent::rename($path1, $path2);
			}
		}
		$permissions = $this->file_exists($path2) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		$sourceParent = dirname($path1);
		if ($sourceParent === '.') {
			$sourceParent = '';
		}
		return $this->checkPermissions($sourceParent, Constants::PERMISSION_DELETE) &&
			$this->checkPermissions($path1, Constants::PERMISSION_UPDATE & Constants::PERMISSION_READ) &&
			$this->checkPermissions($path2, $permissions) &&
			parent::rename($path1, $path2);
	}

	public function opendir($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		$handle = parent::opendir($path);
		$items = [];
		while ($file = readdir($handle)) {
			if ($file !== '.' && $file !== '..') {
				if ($this->checkPermissions(trim($path . '/' . $file, '/'), Constants::PERMISSION_READ)) {
					$items[] = $file;
				}
			}
		}

		return IteratorDirectory::wrap($items);
	}

	public function copy($path1, $path2) {
		$permissions = $this->file_exists($path2) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path2, $permissions) &&
			$this->checkPermissions($path1, Constants::PERMISSION_READ) &&
			parent::copy($path1, $path2);
	}

	public function touch($path, $mtime = null) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path, $permissions) && parent::touch($path, $mtime);
	}

	public function mkdir($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_CREATE) && parent::mkdir($path);
	}

	public function rmdir($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE)
			&& $this->canDeleteTree($path)
			&& parent::rmdir($path);
	}

	public function unlink($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE)
			&& $this->canDeleteTree($path)
			&& parent::unlink($path);
	}

	/**
	 * When deleting we need to ensure that there is no file inside the folder being deleted that misses delete permissions
	 * This check is fairly expensive so we only do it for the actual delete and not metadata operations
	 *
	 * @param string $path
	 * @return int
	 */
	private function canDeleteTree(string $path): int {
		return $this->aclManager->getPermissionsForTree($path) & Constants::PERMISSION_DELETE;
	}

	public function file_put_contents($path, $data) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path, $permissions) ? parent::file_put_contents($path, $data) : false;
	}

	public function fopen($path, $mode) {
		if ($mode === 'r' or $mode === 'rb') {
			$permissions = Constants::PERMISSION_READ;
		} else {
			$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		}
		return $this->checkPermissions($path, $permissions) ? parent::fopen($path, $mode) : false;
	}

	public function writeStream(string $path, $stream, int $size = null): int {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path, $permissions) ? parent::writeStream($path, $stream, $size) : 0;
	}

	/**
	 * get a cache instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the cache
	 * @return \OC\Files\Cache\Cache
	 */
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		$sourceCache = parent::getCache($path, $storage);
		return new ACLCacheWrapper($sourceCache, $this->aclManager, $this->inShare);
	}

	public function getMetaData($path) {
		$data = parent::getMetaData($path);

		if ($data && isset($data['permissions'])) {
			$data['scan_permissions'] = isset($data['scan_permissions']) ? $data['scan_permissions'] : $data['permissions'];
			$data['permissions'] &= $this->getACLPermissionsForPath($path);
		}
		return $data;
	}

	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this->storage;
		}
		return parent::getScanner($path, $storage);
	}

	public function is_dir($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::is_dir($path);
	}

	public function is_file($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::is_file($path);
	}

	public function stat($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::stat($path);
	}

	public function filetype($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filetype($path);
	}

	public function filesize($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filesize($path);
	}

	public function file_exists($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::file_exists($path);
	}

	public function filemtime($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		parent::filemtime($path);
	}

	public function file_get_contents($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::file_get_contents($path);
	}

	public function getMimeType($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getMimeType($path);
	}

	public function hash($type, $path, $raw = false) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		parent::hash($type, $path, $raw);
	}

	public function getETag($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getETag($path);
	}

	public function getDirectDownload($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getDirectDownload($path);
	}

	public function getDirectoryContent($directory): \Traversable {
		foreach ($this->getWrapperStorage()->getDirectoryContent($directory) as $data) {
			$data['scan_permissions'] = isset($data['scan_permissions']) ? $data['scan_permissions'] : $data['permissions'];
			$data['permissions'] &= $this->getACLPermissionsForPath(rtrim($directory, '/') . '/' . $data['name']);

			yield $data;
		}
	}
}
