<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Cache;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\ObjectStore\PrimaryObjectStoreConfig;
use OC\Files\Storage\Local;
use OC\Files\Storage\Wrapper\Jail;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCA\GroupFolders\AppInfo\Application;
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
	private array $cachedFolders = [];

	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IAppConfig $appConfig,
		private readonly ACLManagerFactory $aclManagerFactory,
		private readonly IConfig $config,
		private readonly PrimaryObjectStoreConfig $primaryObjectStoreConfig,
	) {
		$this->enableEncryption = $this->appConfig->getValueString(Application::APP_ID, 'enable_encryption', 'false') === 'true';
	}

	/**
	 * @return array{storage_id: int, root_id: int}
	 */
	public function initRootAndStorageForFolder(int $folderId, bool $separateStorage, array $options): array {
		$storage = $this->getBaseStorageForFolder($folderId, $separateStorage, init: true, options: $options);
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
	 * @param 'files'|'trash'|'versions'|'' $type
	 */
	public function getBaseStorageForFolder(
		int $folderId,
		bool $separateStorage,
		?FolderDefinition $folder = null,
		?IUser $user = null,
		bool $inShare = false,
		string $type = 'files',
		bool $init = false,
		array $options = [],
	): IStorage {
		if ($separateStorage) {
			return $this->getBaseStorageForFolderSeparate($folderId, $folder, $user, $inShare, $type, $init, $options);
		} else {
			return $this->getBaseStorageForFolderRootJail($folderId, $folder, $user, $inShare, $type);
		}
	}

	/**
	 * @param 'files'|'trash'|'versions'|'' $type
	 */
	private function getBaseStorageForFolderSeparate(
		int $folderId,
		?FolderDefinition $folder = null,
		?IUser $user = null,
		bool $inShare = false,
		string $type = 'files',
		bool $init = false,
		array $options = [],
	): IStorage {
		if ($this->primaryObjectStoreConfig->hasObjectStore()) {
			$storage = $this->getBaseStorageForFolderSeparateStorageObject($folderId, $init, $options['bucket'] ?? null);
		} else {
			$storage = $this->getBaseStorageForFolderSeparateStorageLocal($folderId, $init);
		}

		if ($folder?->acl && $user) {
			$aclManager = $this->aclManagerFactory->getACLManager($user);
			$storage = new ACLStorageWrapper([
				'storage' => $storage,
				'acl_manager' => $aclManager,
				'in_share' => $inShare,
				'folder_id' => $folderId,
				'storage_id' => $storage->getCache()->getNumericStorageId(),
			]);
		}

		if ($this->enableEncryption) {
			return new GroupFolderEncryptionJail([
				'storage' => $storage,
				'root' => $type,
			]);
		} else {
			return new Jail([
				'storage' => $storage,
				'root' => $type,
			]);
		}
	}

	private function getBaseStorageForFolderSeparateStorageLocal(
		int $folderId,
		bool $init = false,
	): IStorage {
		$dataDirectory = $this->config->getSystemValue('datadirectory');
		$rootPath = $dataDirectory . '/__groupfolders/' . $folderId;
		if ($init) {
			$result = mkdir($rootPath . '/files', recursive:  true);
			$result = $result && mkdir($rootPath . '/trash');
			$result = $result && mkdir($rootPath . '/versions');

			if (!$result) {
				throw new \Exception('Failed to create base directories for group folder ' . $folderId);
			}
		}

		$storage = new Local([
			'datadir' => $rootPath,
		]);

		if ($init) {
			$storage->getScanner()->scan('');
		}
		return $storage;
	}

	private function getBaseStorageForFolderSeparateStorageObject(
		int $folderId,
		bool $init = false,
		?string $bucket = null,
	): IStorage {
		$objectStoreConfig = $this->primaryObjectStoreConfig->getObjectStoreConfiguration($this->getObjectStorageKey($folderId));

		$bucketKey = 'object_store_bucket_' . $folderId;
		$savedBucket = $this->appConfig->getValueString(Application::APP_ID, $bucketKey);
		if ($savedBucket) {
			$objectStoreConfig['arguments']['bucket'] = $savedBucket;
		} elseif ($objectStoreConfig['arguments']['multibucket'] || $bucket !== null) {
			$objectStoreConfig['arguments']['bucket'] = $this->getObjectStorageBucket($folderId, $objectStoreConfig, $bucket);
		}

		$objectStore = $this->primaryObjectStoreConfig->buildObjectStore($objectStoreConfig);
		$arguments = array_merge($objectStoreConfig['arguments'], [
			'objectstore' => $objectStore,
		]);
		$arguments['storageid'] = 'object::groupfolder:' . $folderId . '.' . $objectStore->getStorageId();

		$storage = new ObjectStoreStorage($arguments);

		if ($init) {
			$result = $storage->mkdir('files');
			$result = $result && $storage->mkdir('trash');
			$result = $result && $storage->mkdir('versions');

			if (!$result) {
				throw new \Exception('Failed to create base directories for group folder ' . $folderId);
			}
		}
		return $storage;
	}

	/**
	 * @param 'files'|'trash'|'versions'|'' $type
	 */
	private function getBaseStorageForFolderRootJail(
		int $folderId,
		?FolderDefinition $folder = null,
		?IUser $user = null,
		bool $inShare = false,
		string $type = 'files',
	): IStorage {
		if (isset($this->cachedFolders['root'])) {
			$parentFolder = $this->cachedFolders['root'];
		} else {
			try {
				/** @var Folder $parentFolder */
				$parentFolder = $this->rootFolder->get('__groupfolders');
			} catch (NotFoundException) {
				$parentFolder = $this->rootFolder->newFolder('__groupfolders');
			}
			$this->cachedFolders['root'] = $parentFolder;
		}

		if ($type !== 'files') {
			if (isset($this->cachedFolders[$type])) {
				$parentFolder = $this->cachedFolders[$type];
			} else {
				try {
					/** @var Folder $parentFolder */
					$parentFolder = $parentFolder->get($type);
				} catch (NotFoundException) {
					$parentFolder = $parentFolder->newFolder($type);
				}
				$this->cachedFolders[$type] = $parentFolder;
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
				'folder_id' => $folderId,
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
		foreach (['files', 'trash', 'versions', ''] as $type) {
			$storage = $this->getBaseStorageForFolder($folder->id, $folder->useSeparateStorage(), $folder, null, false, $type);
			/** @var Cache $cache */
			$cache = $storage->getCache();
			$cache->clear();
			$storage->rmdir('');
		}
	}

	private function getObjectStorageKey(int $folderId): string {
		$configs = $this->primaryObjectStoreConfig->getObjectStoreConfigs();
		if ($this->primaryObjectStoreConfig->hasMultipleObjectStorages()) {
			$configKey = 'object_store_key_' . $folderId;
			$storageConfigKey = $this->appConfig->getValueString(Application::APP_ID, $configKey);
			if (!$storageConfigKey) {
				$storageConfigKey = isset($configs['groupfolders']) ? $this->primaryObjectStoreConfig->resolveAlias('groupfolders') : $this->primaryObjectStoreConfig->resolveAlias('default');
				$this->appConfig->setValueString(Application::APP_ID, $configKey, $storageConfigKey);
			}
			return $storageConfigKey;
		} else {
			return 'default';
		}
	}

	private function getObjectStorageBucket(int $folderId, array $objectStoreConfig, ?string $overwriteBucket = null): string {
		$bucketKey = 'object_store_bucket_' . $folderId;
		$bucket = $this->appConfig->getValueString(Application::APP_ID, $bucketKey);
		if (!$bucket) {
			if ($overwriteBucket !== null) {
				$bucket = $overwriteBucket;
			} else {
				$bucketBase = $objectStoreConfig['arguments']['bucket'] ?? '';
				$bucket = $bucketBase . $this->calculateBucketNum((string)$folderId, $objectStoreConfig);
			}

			$this->appConfig->setValueString(Application::APP_ID, $bucketKey, $bucket);
		}
		return $bucket;
	}

	// logic taken from OC\Files\ObjectStore\Mapper which we can't use because it requires an IUser
	private function calculateBucketNum(string $key, array $objectStoreConfig): string {
		$numBuckets = $objectStoreConfig['arguments']['num_buckets'] ?? 64;

		// Get the bucket config and shift if provided.
		// Allow us to prevent writing in old filled buckets
		$minBucket = (int)($objectStoreConfig['arguments']['min_bucket'] ?? 0);

		$hash = md5($key);
		$num = hexdec(substr($hash, 0, 4));
		return (string)(($num % ($numBuckets - $minBucket)) + $minBucket);
	}
}
