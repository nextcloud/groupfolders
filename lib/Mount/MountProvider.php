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
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\Richdocuments\Db\WopiMapper;
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
		private readonly MountPathResolver $mountPathResolver,
		private readonly bool $allowRootShare,
		private readonly bool $enableEncryption,
	) {
	}

	#[Override]
	public function getMountsForUser(IUser $user, IStorageFactory $loader): array {
		$folders = $this->folderManager->getFoldersForUser($user);

		// The folder definitions already carry `aclDefaultNoPermission`; prime the
		// cache so the per-folder `getBasePermission()` below does not re-query it.
		foreach ($folders as $folder) {
			$this->folderManager->primeAclDefaultNoPermission($folder);
		}

		$mountPaths = $this->mountPathResolver->resolveMountPaths($folders);
		// folders that cannot be mounted because their path is taken by another folder
		$folders = array_values(array_filter($folders, fn (FolderDefinitionWithPermissions $folder): bool => isset($mountPaths[$folder->id])));

		foreach ($folders as $folder) {
			if ($mountPaths[$folder->id] !== $folder->mountPoint) {
				$this->ensurePrefixFolderExists($user, $mountPaths);
				break;
			}
		}

		$mountPoints = array_map(fn (FolderDefinitionWithPermissions $folder): string => 'files/' . $mountPaths[$folder->id], $folders);
		$conflicts = $this->findConflictsForUser($user, $mountPoints);

		/** @var array<FolderDefinitionWithPermissions> $foldersWithAcl */
		$foldersWithAcl = array_filter($folders, fn (FolderDefinitionWithPermissions $folder): bool => $folder->acl);
		$rootFileIds = array_map(fn (FolderDefinitionWithPermissions $folder): int => $folder->rootId, $foldersWithAcl);
		$aclManager = $this->aclManagerFactory->getACLManager($user);
		$rootRules = $aclManager->getRulesByFileIds($rootFileIds);

		return array_map(function (FolderDefinitionWithPermissions $folder) use ($user, $loader, $conflicts, $aclManager, $rootRules, $mountPaths): IMountPoint {
			// check for existing files in the user home and rename them if needed
			$originalFolderName = $mountPaths[$folder->id];
			if (in_array($originalFolderName, $conflicts)) {
				/** @var IStorage $userStorage */
				$userStorage = $this->mountProviderCollection->getHomeMountForUser($user)->getStorage();
				$userCache = $userStorage->getCache();
				$i = 1;
				$folderName = $originalFolderName . ' (' . $i++ . ')';

				while ($userCache->inCache("files/$folderName")) {
					$folderName = $originalFolderName . ' (' . $i++ . ')';
				}

				$userStorage->rename("files/$originalFolderName", "files/$folderName");
				$userCache->move("files/$originalFolderName", "files/$folderName");
				$userStorage->getPropagator()->propagateChange("files/$folderName", time());
			}

			return $this->getMount(
				$folder,
				'/' . $user->getUID() . '/files/' . $originalFolderName,
				$loader,
				$user,
				$aclManager,
				$rootRules[$folder->storageId] ?? [],
			);
		}, $folders);
	}

	private function getCurrentUID(): ?string {
		try {
			// wopi requests are not logged in, instead we need to get the editor user from the access token
			if (str_contains($this->request->getRawPathInfo(), 'apps/richdocuments/wopi') && class_exists(WopiMapper::class)) {
				$wopiMapper = Server::get(WopiMapper::class);
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

	/**
	 * @param array<string, Rule[]> $rootRules
	 */
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
			/** @phpstan-ignore assignOp.invalid */
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
		FolderDefinition $folder,
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
		FolderDefinition $folder,
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
	 * Make sure the configured parent folder (e.g. "TeamFolders") exists as a real folder
	 * in the user's home, so the team folders can be reached by path directly.
	 * A regular *file* blocking the path is renamed out of the way.
	 *
	 * If a group folder is mounted at (a part of) the parent folder, the team folders are
	 * nested inside that group folder like any other nested group folder and nothing is
	 * created in the user's home from there on.
	 *
	 * @param array<int, string> $mountPaths the paths the user's folders are mounted at
	 */
	private function ensurePrefixFolderExists(IUser $user, array $mountPaths): void {
		$prefix = $this->mountPathResolver->getPrefix();
		if ($prefix === '' || in_array(MountPathResolver::ROOT_MOUNT_POINT, $mountPaths, true)) {
			// a folder mounted at "/" replaces the user's home folder
			return;
		}

		$mountedPaths = array_flip($mountPaths);

		try {
			/** @var IStorage $userStorage */
			$userStorage = $this->mountProviderCollection->getHomeMountForUser($user)->getStorage();
			$userCache = $userStorage->getCache();
			$current = 'files';
			$relative = '';
			foreach (explode('/', $prefix) as $segment) {
				$relative = ltrim($relative . '/' . $segment, '/');
				if (isset($mountedPaths[$relative])) {
					return;
				}

				$current .= '/' . $segment;
				$entry = $userCache->get($current);

				if ($entry !== false && $entry->getMimeType() !== ICacheEntry::DIRECTORY_MIMETYPE) {
					$i = 1;
					do {
						$target = $current . ' (' . $i++ . ')';
					} while ($userCache->inCache($target));

					$userStorage->rename($current, $target);
					$userCache->move($current, $target);
					$userStorage->getPropagator()->propagateChange($target, time());
					$entry = false;
				}

				if ($entry === false) {
					if (!$userStorage->is_dir($current) && !$userStorage->mkdir($current)) {
						return;
					}

					$userStorage->getUpdater()->update($current);
				}
			}
		} catch (\Throwable) {
			// never break the filesystem setup because of this,
			// the view still creates missing parents when listing the home folder
		}
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
			/** @var array<int, string> $rows */
			$rows = $query->executeQuery()->fetchAll(PDO::FETCH_COLUMN);
			array_push($paths, ...$rows);
		}

		return array_map(function (string $path): string {
			return substr($path, 6); // strip leading "files/"
		}, $paths);
	}

	#[Override]
	public function getMountsForPath(string $setupPathHint, bool $forChildren, array $mountProviderArgs, IStorageFactory $loader): array {
		/** @var IUser $user */
		$user = $mountProviderArgs[0]->mountInfo->getUser();

		/** @var list<string> $mountPointCandidates mount points (as stored in the database) to look up */
		$mountPointCandidates = [];
		/** @var array<string, true> $requestedPaths requested paths relative to the user's files */
		$requestedPaths = [];

		/** @var MountProviderArgs $mountProviderArg */
		foreach ($mountProviderArgs as $mountProviderArg) {
			$parts = explode('/', $mountProviderArg->mountInfo->getMountPoint());
			if ($parts[2] !== 'files' || $parts[1] !== $user->getUID()) {
				continue;
			}

			$relativePath = implode('/', array_splice($parts, 3, -1));
			if ($relativePath === '') {
				// a folder mounted as the user's home folder
				$relativePath = MountPathResolver::ROOT_MOUNT_POINT;
			}

			$requestedPaths[$relativePath] = true;
			// the database only knows the stored mount point, so look up every folder that can be
			// mounted at this path, including the folders it can collide with
			array_push($mountPointCandidates, ...$this->mountPathResolver->getMountPointCandidates($relativePath));
		}

		$mountPointCandidates = array_values(array_unique($mountPointCandidates));
		if (count($mountPointCandidates) === 0) {
			return [];
		}

		/** @var array<string, IMountPoint> $mounts */
		$mounts = [];

		$aclManager = $this->aclManagerFactory->getACLManager($user);
		$folders = $this->folderManager->getFoldersForUser($user, null, $mountPointCandidates);
		$mountPaths = $this->mountPathResolver->resolveMountPaths($folders);

		foreach ($folders as $folder) {
			$mountPath = $mountPaths[$folder->id] ?? null;
			if ($mountPath === null || !isset($requestedPaths[$mountPath])) {
				// not mounted, or only found because it can collide with a requested folder
				continue;
			}

			$this->folderManager->primeAclDefaultNoPermission($folder);

			$mountPoint = '/' . $user->getUID() . '/files/' . $mountPath;

			$mounts[$mountPoint] = $this->getMount(
				$folder,
				$mountPoint,
				$loader,
				$user,
				$aclManager,
				$folder->acl ? $aclManager->getRulesByFileIds([$folder->rootId])[$folder->storageId] ?? [] : [],
			);
		}

		return $mounts;
	}
}
