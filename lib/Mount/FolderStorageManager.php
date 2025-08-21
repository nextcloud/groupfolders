<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Storage\Wrapper\Jail;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\IAppConfig;

class FolderStorageManager {
	private readonly bool $enableEncryption;

	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IAppConfig $appConfig,
	) {
		$this->enableEncryption = $this->appConfig->getValueBool('groupfolders', 'enable_encryption');
	}

	/**
	 * @return array{storage_id: int, root_id: int}
	 */
	public function getRootAndStorageIdForFolder(int $folderId): array {
		$storage = $this->getBaseStorageForFolder($folderId);
		$cache = $storage->getCache();
		$id = $cache->getId('');
		if ($id === -1) {
			$storage->getScanner()->scan('');
			$id = $cache->getId('');
			if ($id === -1) {
				throw new \Exception('Group folder root is not in cache even after scanning for folder ' . $folderId);
			}
		}
		return [
			'storage_id' => $cache->getNumericStorageId(),
			'root_id' => $id,
		];
	}

	public function getBaseStorageForFolder(int $folderId): IStorage {
		try {
			/** @var Folder $parentFolder */
			$parentFolder = $this->rootFolder->get('__groupfolders');
		} catch (NotFoundException) {
			$parentFolder = $this->rootFolder->newFolder('__groupfolders');
		}

		try {
			/** @var Folder $folder */
			$folder = $parentFolder->get((string)$folderId);
		} catch (NotFoundException) {
			$folder = $parentFolder->newFolder((string)$folderId);
		}
		$rootStorage = $folder->getStorage();
		$rootPath = $folder->getInternalPath();

		if ($this->enableEncryption) {
			return new GroupFolderEncryptionJail([
				'storage' => $rootStorage,
				'root' => $rootPath,
			]);
		} else {
			return new Jail([
				'storage' => $rootStorage,
				'root' => $rootPath,
			]);
		}
	}
}
