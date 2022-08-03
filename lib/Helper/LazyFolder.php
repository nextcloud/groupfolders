<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Helper;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class LazyFolder implements Folder {
	private IRootFolder $rootFolder;
	private ?Folder $folder = null;

	public function __construct(IRootFolder $rootFolder) {
		$this->rootFolder = $rootFolder;
	}

	private function getFolder(): Folder {
		if ($this->folder === null) {
			try {
				$this->folder = $this->rootFolder->get('__groupfolders');
			} catch (NotFoundException $e) {
				$this->folder = $this->rootFolder->newFolder('__groupfolders');
			}
		}

		return $this->folder;
	}

	/**
	 * Magic method to first get the real rootFolder and then
	 * call $method with $args on it
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public function __call($method, $args) {
		$folder = $this->getFolder();

		return call_user_func_array([$folder, $method], $args);
	}

	public function getMtime() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getMimetype() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getMimePart() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isEncrypted() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getType() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isShared() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isMounted() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getMountPoint() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getOwner() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getChecksum() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getExtension(): string {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getCreationTime(): int {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getUploadTime(): int {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getFullPath($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getRelativePath($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isSubNode($node) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getDirectoryListing() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function get($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function nodeExists($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function newFolder($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function newFile($path, $content = null) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function search($query) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function searchByMime($mimetype) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function searchByTag($tag, $userId) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getById($id) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getFreeSpace() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isCreatable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getNonExistingName($name) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getRecent($limit, $offset = 0) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function move($targetPath) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function delete() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function copy($targetPath) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function touch($mtime = null) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getStorage() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getPath() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getInternalPath() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getId() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function stat() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getSize($includeMounts = true) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getEtag() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getPermissions() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isReadable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isUpdateable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isDeletable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isShareable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getParent() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getName() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function lock($type) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function changeLock($targetType) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function unlock($type) {
		return $this->__call(__FUNCTION__, func_get_args());
	}
}
