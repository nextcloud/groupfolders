<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use Exception;
use OC;
use OC\Files\Storage\Wrapper\PermissionsMask;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IPartialMountProvider;
use OCP\Files\Config\MountProviderArgs;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Server;
use Override;
use PDO;

class MountProvider implements IMountProvider, IPartialMountProvider {
	public function __construct(
		private readonly FolderManager $folderManager,
		private readonly ACLManagerFactory $aclManagerFactory,
		private readonly IUserSession $userSession,
		private readonly IRequest $request,
		private readonly IMountProviderCollection $mountProviderCollection,
		private readonly IDBConnection $connection,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly bool $allowRootShare,
		private readonly bool $enableEncryption,
	) {
	}

	#[Override]
	public function getMountsForUser(IUser $user, IStorageFactory $loader): array {
		$folders = $this->folderManager->getFoldersForUser($user);

		// Single pass to build both mountPoints and ACL data
		$mountPoints = [];
		$rootFileIds = [];

		foreach ($folders as $folder) {
			$mountPoints[] = 'files/' . $folder->mountPoint;
			if ($folder->acl) {
				$rootFileIds[] = $folder->rootId;
			}
		}

		$conflicts = array_flip($this->findConflictsForUser($user, $mountPoints));
		$aclManager = $this->aclManagerFactory->getACLManager($user);
		$rootRules = $rootFileIds
			? $aclManager->getRulesByFileIds($rootFileIds)
			: [];

		$userStorage = null;
		$mounts = [];

		foreach ($folders as $folder) {
			$originalFolderName = $folder->mountPoint;

			if (isset($conflicts[$originalFolderName])) {
				if ($userStorage === null) {
					/** @var IStorage $userStorage */
					$userStorage = $this->mountProviderCollection->getHomeMountForUser($user)->getStorage();
				}

				$i = 1;
				$folderName = $originalFolderName . ' (' . $i++ . ')';
				$cache = $userStorage->getCache();
				while ($cache->inCache('files/' . $folderName)) {
					$folderName = $originalFolderName . ' (' . $i++ . ')';
				}

				$userStorage->rename(
					'files/' . $originalFolderName,
					'files/' . $folderName
				);
				$cache->move(
					'files/' . $originalFolderName,
					'files/' . $folderName
				);
				$userStorage->getPropagator()->propagateChange('files/' . $folderName, time());
			}

			$mounts[] = $this->getMount(
				$folder,
				'/' . $user->getUID() . '/files/' . $originalFolderName,
				$loader,
				$user,
				$aclManager,
				$rootRules[$folder->storageId] ?? [],
			);
		}

		return $mounts;
	}

	private function getCurrentUID(): ?string {
		try {
			// wopi requests are not logged in, instead we need to get the editor user from the access token
			if (str_contains($this->request->getRawPathInfo(), 'apps/richdocuments/wopi') && class_exists('OCA\Richdocuments\Db\WopiMapper')) {
				$wopiMapper = Server::get('OCA\Richdocuments\Db\WopiMapper');
				$token = $this->request->getParam('access_token');
				if ($token) {
					$wopi = $wopiMapper->getPathForToken($token);
					return $wopi->getEditorUid();
				}
			}
		} catch (Exception) {
		}

		$user = $this->userSession->getUser();

		return $user?->getUID();
	}

	public function getMount(
		FolderDefinitionWithPermissions $folder,
		string $mountPoint,
		?IStorageFactory $loader = null,
		?IUser $user = null,
		?ACLManager $aclManager = null,
		array $rootRules = [],
	): IMountPoint {
		$cacheEntry = $folder->rootCacheEntry;

		if ($aclManager && $folder->acl && $user) {
			$aclRootPermissions = $aclManager->getPermissionsForPathFromRules($folder->id, $cacheEntry->getPath(), $rootRules);
			$cacheEntry['permissions'] &= $aclRootPermissions;
		}

		$quotaStorage = $this->getGroupFolderStorage($folder, $user, $cacheEntry);

		$maskedStore = new PermissionsMask([
			'storage' => $quotaStorage,
			'mask' => $folder->permissions,
		]);

		if (!$this->allowRootShare) {
			$maskedStore = new RootPermissionsMask([
				'storage' => $maskedStore,
				'mask' => Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE,
				'folder' => $folder,
			]);
		}

		return new GroupMountPoint(
			$folder,
			$maskedStore,
			$mountPoint,
			null,
			$loader,
			rootId: $folder->rootId,
		);
	}

	public function getTrashMount(
		FolderDefinitionWithPermissions $folder,
		string $mountPoint,
		IStorageFactory $loader,
		?IUser $user,
		?ICacheEntry $cacheEntry = null,
	): IMountPoint {

		$storage = $this->folderStorageManager->getBaseStorageForFolder($folder->id, $folder->useSeparateStorage(), $folder, null, false, 'trash');

		if ($user) {
			$storage->setOwner($user->getUID());
		}

		$trashStorage = $this->getGroupFolderStorage($folder, $user, $cacheEntry, 'trash');

		return new GroupMountPoint(
			$folder,
			$trashStorage,
			$mountPoint,
			null,
			$loader,
		);
	}

	public function getVersionsMount(
		FolderDefinition $folder,
		string $mountPoint,
		IStorageFactory $loader,
		?ICacheEntry $cacheEntry = null,
	): IMountPoint {
		if (!$cacheEntry) {
			$storage = $this->folderStorageManager->getBaseStorageForFolder($folder->id, $folder->useSeparateStorage(), $folder, null, false, 'versions');
			$cacheEntry = $storage->getCache()->get('');
			if (!$cacheEntry) {
				$storage->getScanner()->scan('');
				$cacheEntry = $storage->getCache()->get('');
				if (!$cacheEntry) {
					throw new Exception('Group folder version root is not in cache even after scanning for folder ' . $folder->id);
				}
			}
		}

		$versionStorage = $this->getGroupFolderStorage(
			FolderDefinitionWithPermissions::fromFolder($folder, $cacheEntry, Constants::PERMISSION_ALL),
			null, $cacheEntry,
			'versions'
		);

		return new GroupMountPoint(
			$folder,
			$versionStorage,
			$mountPoint,
			null,
			$loader,
		);
	}

	/**
	 * @param 'files'|'trash'|'versions' $type
	 */
	public function getGroupFolderStorage(
		FolderDefinitionWithPermissions $folder,
		?IUser $user,
		?ICacheEntry $rootCacheEntry,
		string $type = 'files',
	): IStorage {
		if ($user) {
			$inShare = !OC::$CLI && ($this->getCurrentUID() === null || $this->getCurrentUID() !== $user->getUID());
			$baseStorage = $this->folderStorageManager->getBaseStorageForFolder($folder->id, $folder->useSeparateStorage(), $folder, $user, $inShare, $type);
			$baseStorage->setOwner($user->getUID());
		} else {
			$baseStorage = $this->folderStorageManager->getBaseStorageForFolder($folder->id, $folder->useSeparateStorage(), $folder, null, false, $type);
		}

		$storageClass = $this->enableEncryption
			? GroupFolderStorage::class
			: GroupFolderNoEncryptionStorage::class;

		return new $storageClass([
			'storage' => $baseStorage,
			'quota' => $folder->quota,
			'folder' => $folder,
			'rootCacheEntry' => $rootCacheEntry,
			'userSession' => $this->userSession,
			'mountOwner' => $user,
		]);
	}

	/**
	 * @param string[] $mountPoints
	 * @return string[] An array of paths.
	 */
	private function findConflictsForUser(IUser $user, array $mountPoints): array {
		$userHome = $this->mountProviderCollection->getHomeMountForUser($user);

		$pathHashes = array_map(md5(...), $mountPoints);

		$query = $this->connection->getQueryBuilder();
		$query->select('path')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createNamedParameter($userHome->getNumericStorageId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('path_hash', $query->createParameter('chunk')));

		$paths = [];
		foreach (array_chunk($pathHashes, 1000) as $chunk) {
			$query->setParameter('chunk', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			array_push($paths, ...$query->executeQuery()->fetchAll(PDO::FETCH_COLUMN));
		}

		return array_map(function (string $path): string {
			return substr($path, 6); // strip leading "files/"
		}, $paths);
	}

	#[Override]
	public function getMountsForPath(string $setupPathHint, bool $forChildren, array $mountProviderArgs, IStorageFactory $loader): array {
		/** @var array<string, IMountPoint> $mounts */
		$mounts = [];

		/** @var array<string, ACLManager> $userAclManagers */
		$userAclManagers = [];

		/** @var MountProviderArgs $mountProviderArg */
		foreach ($mountProviderArgs as $mountProviderArg) {
			$user = $mountProviderArg->mountInfo->getUser();

			$parts = explode('/', $mountProviderArg->mountInfo->getMountPoint());
			if ($parts[2] !== 'files' || $parts[1] !== $user->getUID()) {
				continue;
			}

			$relativePath = implode('/', array_splice($parts, 3, -1));
			if ($relativePath === '') {
				$relativePath = '/';
			}

			foreach ($this->folderManager->getFoldersForUser($user, null, $relativePath) as $folder) {
				$mountPoint = '/' . $user->getUID() . '/files/' . $folder->mountPoint;
				if (isset($mounts[$mountPoint])) {
					continue;
				}

				$userAclManagers[$user->getUID()] ??= $this->aclManagerFactory->getACLManager($user);
				$aclManager = $userAclManagers[$user->getUID()];

				$mounts[$mountPoint] = $this->getMount(
					$folder,
					$mountPoint,
					$loader,
					$user,
					$aclManager,
					$folder->acl ? $aclManager->getRulesByFileIds([$folder->rootId]) : [],
				);
			}
		}

		return $mounts;
	}
}
