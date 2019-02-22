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

	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->aclManager = $arguments['acl_manager'];
	}

	private function getACLPermissionsForPath(string $path) {
		$permissions = $this->aclManager->getACLPermissionsForPath($path);

		// if there is no read permissions, than deny everything
		return $permissions & Constants::PERMISSION_READ ? $permissions : 0;
	}

	private function checkPermissions(string $path, int $permissions) {
		return ($this->getACLPermissionsForPath($path) & $permissions) === $permissions;
	}

	public function isUpdatable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_UPDATE) and parent::isUpdatable($path);
	}

	public function isCreatable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_CREATE) and parent::isCreatable($path);
	}

	public function isDeletable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE) and parent::isDeletable($path);
	}

	public function isSharable($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_SHARE) and parent::isSharable($path);
	}

	public function getPermissions($path) {
		return $this->storage->getPermissions($path) & $this->getACLPermissionsForPath($path);
	}

	public function rename($path1, $path2) {
		if (strpos($path1, $path2) === 0) {
			$part = substr($path1, strlen($path2));
			//This is a rename of the transfer file to the original file
			if (strpos($part, '.ocTransferId') === 0) {
				return $this->checkPermissions($path2, Constants::PERMISSION_CREATE) and parent::rename($path1, $path2);
			}
		}
		$permissions = $this->file_exists($path2) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path1, Constants::PERMISSION_UPDATE & Constants::PERMISSION_READ) and
			$this->checkPermissions($path2, $permissions) and
			parent::rename($path1, $path2);
	}

	public function opendir($path) {
		if (!$this->checkPermissions($path, Constants::PERMISSION_READ)) {
			return false;
		}

		$handle = parent::opendir($path);
		$items = [];
		while ($file = readdir($handle)) {
			if ($file != '.' && $file != '..') {
				if ($this->checkPermissions(trim($path . '/' . $file, '/'), Constants::PERMISSION_READ)) {
					$items[] = $file;
				}
			}
		}

		return IteratorDirectory::wrap($items);
	}

	public function copy($path1, $path2) {
		$permissions = $this->file_exists($path2) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path2, $permissions) and
			$this->checkPermissions($path1, Constants::PERMISSION_READ) and
			parent::copy($path1, $path2);
	}

	public function touch($path, $mtime = null) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($path, $permissions) and parent::touch($path, $mtime);
	}

	public function mkdir($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_CREATE) and parent::mkdir($path);
	}

	public function rmdir($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE) and parent::rmdir($path);
	}

	public function unlink($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_DELETE) and parent::unlink($path);
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
		return new ACLCacheWrapper($sourceCache, $this->aclManager);
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
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::stat($path);
	}

	public function filetype($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::filetype($path);
	}

	public function filesize($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::filesize($path);
	}

	public function file_exists($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::file_exists($path);
	}

	public function filemtime($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::filemtime($path);
	}

	public function file_get_contents($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::file_get_contents($path);
	}

	public function getMimeType($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::getMimeType($path);
	}

	public function hash($type, $path, $raw = false) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::hash($type, $path, $raw);
	}

	public function getETag($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::getETag($path);
	}

	public function getDirectDownload($path) {
		return $this->checkPermissions($path, Constants::PERMISSION_READ) &&
			parent::getDirectDownload($path);
	}
}
