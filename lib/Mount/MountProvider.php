<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\Mount;

use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\PermissionsMask;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
use OCP\ICache;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserSession;

class MountProvider implements IMountProvider {
	/** @var IGroupManager */
	private $groupProvider;

	/** @var callable */
	private $rootProvider;

	/** @var Folder|null */
	private $root = null;

	/** @var FolderManager */
	private $folderManager;

	private $aclManagerFactory;

	private $userSession;

	private $request;

	private $session;

	private $mountProviderCollection;
	private $connection;
	private ICache $cache;
	private ?int $rootStorageId = null;
	private bool $allowRootShare;
	private bool $enableEncryption;

	public function __construct(
		IGroupManager $groupProvider,
		FolderManager $folderManager,
		callable $rootProvider,
		ACLManagerFactory $aclManagerFactory,
		IUserSession $userSession,
		IRequest $request,
		ISession $session,
		IMountProviderCollection $mountProviderCollection,
		IDBConnection $connection,
		ICache $cache,
		bool $allowRootShare,
		bool $enableEncryption
	) {
		$this->groupProvider = $groupProvider;
		$this->folderManager = $folderManager;
		$this->rootProvider = $rootProvider;
		$this->aclManagerFactory = $aclManagerFactory;
		$this->userSession = $userSession;
		$this->request = $request;
		$this->session = $session;
		$this->mountProviderCollection = $mountProviderCollection;
		$this->connection = $connection;
		$this->cache = $cache;
		$this->allowRootShare = $allowRootShare;
		$this->enableEncryption = $enableEncryption;
	}

	private function getRootStorageId(): int {
		if ($this->rootStorageId === null) {
			$cached = $this->cache->get("root_storage_id");
			if ($cached !== null) {
				$this->rootStorageId = $cached;
			} else {
				$id = $this->getRootFolder()->getStorage()->getCache()->getNumericStorageId();
				$this->cache->set("root_storage_id", $id);
				$this->rootStorageId = $id;
			}
		}
		return $this->rootStorageId;
	}

	/**
	 * @return list<array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}>
	 */
	public function getFoldersForUser(IUser $user): array {
		return $this->folderManager->getFoldersForUser($user, $this->getRootStorageId());
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		// having the folders sorted by mountpoint is required for nested conflict resolution
		usort($folders, function ($a, $b) {
			return $a['mount_point'] <=> $b['mount_point'];
		});

		$mountPoints = array_map(function (array $folder) {
			return 'files/' . $folder['mount_point'];
		}, $folders);
		$conflicts = $this->findConflictsInHome($user, $mountPoints);
		$nestedConflicts = $this->findNestedConflicts($this->getRootStorageId(), $folders);

		$mounts = [];
		foreach ($folders as $folder) {
			$folderId = $folder['folder_id'];

			// check for existing files in the user home and rename them if needed
			$originalFolderName = $folder['mount_point'];
			if (in_array($originalFolderName, $conflicts)) {
				/** @var IStorage $userStorage */
				$userStorage = $this->mountProviderCollection->getHomeMountForUser($user)->getStorage();
				$this->resolveConflictInStorage($userStorage, "files/" . $folder['mount_point']);
			}

			$mount = $this->getMount(
				$folderId,
				'/' . $user->getUID() . '/files/' . $folder['mount_point'],
				$folder['permissions'],
				$folder['quota'],
				$folder['rootCacheEntry'],
				$loader,
				$folder['acl'],
				$user
			);

			if (isset($nestedConflicts[$folderId])) {
				foreach ($nestedConflicts[$folderId] as $conflictPath) {
					$this->resolveConflictInStorage($mount->getStorage(), $conflictPath);
				}
			}

			$mounts[$folderId] = $mount;
		}

		return array_values(array_filter($mounts));
	}

	/**
	 * Find a new non-existing name for $path and rename it
	 *
	 * only works for folders
	 *
	 * @param IStorage $storage
	 * @param string $path
	 * @return void
	 */
	private function resolveConflictInStorage(IStorage $storage, string $path): void {
		$cache = $storage->getCache();
		$i = 1;
		$folderName = $path . ' (' . $i++ . ')';

		while ($cache->inCache($folderName)) {
			$folderName = $path . ' (' . $i++ . ')';
		}

		$storage->rename($path, $folderName);
		$cache->move($path, $folderName);
		$storage->getPropagator()->propagateChange($folderName, time());
	}

	private function getCurrentUID(): ?string {
		try {
			// wopi requests are not logged in, instead we need to get the editor user from the access token
			if (strpos($this->request->getRawPathInfo(), 'apps/richdocuments/wopi') && class_exists('OCA\Richdocuments\Db\WopiMapper')) {
				$wopiMapper = \OC::$server->get('OCA\Richdocuments\Db\WopiMapper');
				$token = $this->request->getParam('access_token');
				if ($token) {
					$wopi = $wopiMapper->getPathForToken($token);
					return $wopi->getEditorUid();
				}
			}
		} catch (\Exception $e) {
		}

		$user = $this->userSession->getUser();
		return $user ? $user->getUID() : null;
	}

	public function getMount(
		int $id,
		string $mountPoint,
		int $permissions,
		int $quota,
		?ICacheEntry $cacheEntry = null,
		IStorageFactory $loader = null,
		bool $acl = false,
		IUser $user = null
	): ?IMountPoint {
		if (!$cacheEntry) {
			// trigger folder creation
			$folder = $this->getFolder($id);
			if ($folder === null) {
				return null;
			}
			$cacheEntry = $this->getRootFolder()->getStorage()->getCache()->get($folder->getId());
		}

		$storage = $this->getRootFolder()->getStorage();

		$rootPath = $this->getJailPath($id);

		// apply acl before jail
		if ($acl && $user) {
			$inShare = $this->getCurrentUID() === null || $this->getCurrentUID() !== $user->getUID();
			$aclManager = $this->aclManagerFactory->getACLManager($user, $this->getRootStorageId());
			$storage = new ACLStorageWrapper([
				'storage' => $storage,
				'acl_manager' => $aclManager,
				'in_share' => $inShare,
			]);
			$aclRootPermissions = $aclManager->getACLPermissionsForPath($rootPath);
			$cacheEntry['permissions'] &= $aclRootPermissions;
		}

		$baseStorage = new Jail([
			'storage' => $storage,
			'root' => $rootPath,
		]);
		if ($this->enableEncryption) {
			$quotaStorage = new GroupFolderStorage([
				'storage' => $baseStorage,
				'quota' => $quota,
				'folder_id' => $id,
				'rootCacheEntry' => $cacheEntry,
				'userSession' => $this->userSession,
				'mountOwner' => $user,
			]);
		} else {
			$quotaStorage = new GroupFolderNoEncryptionStorage([
				'storage' => $baseStorage,
				'quota' => $quota,
				'folder_id' => $id,
				'rootCacheEntry' => $cacheEntry,
				'userSession' => $this->userSession,
				'mountOwner' => $user,
			]);
		}
		$maskedStore = new PermissionsMask([
			'storage' => $quotaStorage,
			'mask' => $permissions,
		]);

		if (!$this->allowRootShare) {
			$maskedStore = new RootPermissionsMask([
				'storage' => $maskedStore,
				'mask' => Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE,
			]);
		}

		return new GroupMountPoint(
			$id,
			$maskedStore,
			$mountPoint,
			null,
			$loader
		);
	}

	public function getJailPath(int $folderId): string {
		return $this->getRootFolder()->getInternalPath() . '/' . $folderId;
	}

	private function getRootFolder(): Folder {
		if (is_null($this->root)) {
			$rootProvider = $this->rootProvider;
			$this->root = $rootProvider();
		}
		return $this->root;
	}

	public function getFolder(int $id, bool $create = true): ?Node {
		try {
			return $this->getRootFolder()->get((string)$id);
		} catch (NotFoundException $e) {
			if ($create) {
				return $this->getRootFolder()->newFolder((string)$id);
			} else {
				return null;
			}
		}
	}

	/**
	 * @param string[] $mountPoints
	 * @return string[] An array of paths.
	 */
	private function findConflictsInHome(IUser $user, array $mountPoints): array {
		$userHome = $this->mountProviderCollection->getHomeMountForUser($user);

		$pathHashes = array_map('md5', $mountPoints);

		$query = $this->connection->getQueryBuilder();
		$query->select('path')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createNamedParameter($userHome->getNumericStorageId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('path_hash', $query->createParameter('chunk')));

		$paths = [];
		foreach (array_chunk($pathHashes, 1000) as $chunk) {
			$query->setParameter('chunk', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			$paths = array_merge($paths, $query->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
		}

		return array_map(function (string $path): string {
			return substr($path, 6); // strip leading "files/"
		}, $paths);
	}

	/**
	 * @param list<array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}> $folders
	 * @return array<int, list<string>>
	 */
	private function findNestedConflicts(int $rootStorageId, array $folders): array {
		$conflictsByFolder = [];

		// for every folder, create a list of nested folders
		$mountPoints = [];
		$pathsToCheck = [];
		$folderIds = [];
		foreach ($folders as $folder) {
			$mountPoint = $folder['mount_point'];
			$folderId = $folder['folder_id'];
			$folderIds[$mountPoint] = $folderId;

			// because we sorted the folder by mountpoint, parent folders will always occur before children
			foreach ($mountPoints as $possibleParent) {
				if (str_starts_with($mountPoint, $possibleParent)) {
					$parentFolderId = $folderIds[$possibleParent];
					$relativeMountPoint = substr($mountPoint, strlen($possibleParent) + 1);
					$pathsToCheck[] = "__groupfolders/$parentFolderId/$relativeMountPoint";
				}
			}
			$conflictsByFolder[$folderId] = [];
			$mountPoints[] = $mountPoint;
		}

		if (!$pathsToCheck) {
			return [];
		}

		$pathHashes = array_map('md5', $pathsToCheck);

		$query = $this->connection->getQueryBuilder();
		$query->select('path')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createNamedParameter($rootStorageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('path_hash', $query->createParameter('chunk')));

		$paths = [];
		foreach (array_chunk($pathHashes, 1000) as $chunk) {
			$query->setParameter('chunk', $chunk, IQueryBuilder::PARAM_STR_ARRAY);
			foreach ($query->executeQuery()->fetchAll(\PDO::FETCH_COLUMN) as $conflictPath) {
				[, $folderId, $relativePath] = explode('/', $conflictPath, 3);
				$conflictsByFolder[(int)$folderId][] = $relativePath;
			}
			$paths = array_merge($paths, );
		}

		return $conflictsByFolder;
	}
}
