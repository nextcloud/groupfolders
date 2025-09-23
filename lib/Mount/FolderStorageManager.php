<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Cache;
use OC\Files\Storage\Local;
use OC\Files\Storage\Wrapper\Jail;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUser;

class FolderStorageManager {
	private readonly bool $enableEncryption;

	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IAppConfig $appConfig,
		private readonly ACLManagerFactory $aclManagerFactory,
		private readonly IConfig $config,
	) {
		$this->enableEncryption = $this->appConfig->getValueString('groupfolders', 'enable_encryption', 'false') === 'true';
	}

	/**
	 * @return array{storage_id: int, root_id: int}
	 */
	public function getRootAndStorageIdForFolder(int $folderId, bool $separateStorage): array {
		$storage = $this->getBaseStorageForFolder($folderId, $separateStorage);
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

	/**
	 * @param 'files'|'trash'|'versions' $type
	 */
	public function getBaseStorageForFolder(
		int $folderId,
		bool $separateStorage,
		?FolderDefinition $folder = null,
		?IUser $user = null,
		bool $inShare = false,
		string $type = 'files',
	): IStorage {
		if ($separateStorage) {
			return $this->getBaseStorageForFolderSeparateStorageLocal($folderId, $folder, $user, $inShare, $type);
		} else {
			return $this->getBaseStorageForFolderRootJail($folderId, $folder, $user, $inShare, $type);
		}
	}

	/**
	 * @param 'files'|'trash'|'versions' $type
	 */
	public function getBaseStorageForFolderSeparateStorageLocal(
		int $folderId,
		?FolderDefinition $folder = null,
		?IUser $user = null,
		bool $inShare = false,
		string $type = 'files',
	): IStorage {
		$dataDirectory = $this->config->getSystemValue('datadirectory');
		$rootPath = $dataDirectory . '/__groupfolders/' . $folderId;
		$init = !is_dir($rootPath);
		if ($init) {
			mkdir($rootPath, 0777, true);
			mkdir($rootPath . '/files');
			mkdir($rootPath . '/trash');
			mkdir($rootPath . '/versions');
		}

		$storage = new Local([
			'datadir' => $rootPath,
		]);

		if ($init) {
			$storage->getScanner()->scan('');
		}

		if ($folder && $folder->acl && $user) {
			$aclManager = $this->aclManagerFactory->getACLManager($user);
			return new ACLStorageWrapper([
				'storage' => $storage,
				'acl_manager' => $aclManager,
				'in_share' => $inShare,
				'storage_id' => $storage->getCache()->getNumericStorageId(),
			]);
		} else {
			return $storage;
		}
	}

	/**
	 * @param 'files'|'trash'|'versions' $type
	 */
	public function getBaseStorageForFolderRootJail(
		int $folderId,
		?FolderDefinition $folder = null,
		?IUser $user = null,
		bool $inShare = false,
		string $type = 'files',
	): IStorage {
		try {
			/** @var Folder $parentFolder */
			$parentFolder = $this->rootFolder->get('__groupfolders');
		} catch (NotFoundException) {
			$parentFolder = $this->rootFolder->newFolder('__groupfolders');
		}

		if ($type !== 'files') {
			try {
				/** @var Folder $parentFolder */
				$parentFolder = $parentFolder->get($type);
			} catch (NotFoundException) {
				$parentFolder = $parentFolder->newFolder($type);
			}
		}

		try {
			/** @var Folder $storageFolder */
			$storageFolder = $parentFolder->get((string)$folderId);
		} catch (NotFoundException) {
			$storageFolder = $parentFolder->newFolder((string)$folderId);
		}
		$rootStorage = $storageFolder->getStorage();
		$rootPath = $storageFolder->getInternalPath();

		// apply acl before jail, trash doesn't get the ACL wrapper as it does its own ACL filtering
		if ($folder && $folder->acl && $user && $type !== 'trash') {
			$aclManager = $this->aclManagerFactory->getACLManager($user);
			$rootStorage = new ACLStorageWrapper([
				'storage' => $rootStorage,
				'acl_manager' => $aclManager,
				'in_share' => $inShare,
				'storage_id' => $rootStorage->getCache()->getNumericStorageId(),
			]);
		}

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

	public function deleteStoragesForFolder(FolderDefinition $folder): void {
		foreach (['files', 'trash', 'versions'] as $type) {
			$storage = $this->getBaseStorageForFolder($folder->id, $folder->useSeparateStorage(), $folder, null, false, $type);
			/** @var Cache $cache */
			$cache = $storage->getCache();
			$cache->clear();
			$storage->rmdir('');
		}
	}
}
