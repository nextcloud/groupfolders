<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\PermissionsMask;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Folder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Node;
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

/**
 * @psalm-import-type InternalFolder from FolderManager
 */
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
	 * @return list<InternalFolder>
	 */
	public function getFoldersForUser(IUser $user): array {
		return $this->folderManager->getFoldersForUser($user, $this->getRootStorageId());
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		$mountPoints = array_map(function (array $folder) {
			return 'files/' . $folder['mount_point'];
		}, $folders);
		$conflicts = $this->findConflictsForUser($user, $mountPoints);

		$foldersWithAcl = array_filter($folders, function (array $folder) {
			return $folder['acl'];
		});
		$aclRootPaths = array_map(function (array $folder) {
			return $this->getJailPath($folder['folder_id']);
		}, $foldersWithAcl);
		$aclManager = $this->aclManagerFactory->getACLManager($user, $this->getRootStorageId());
		$rootRules = $aclManager->getRelevantRulesForPath($aclRootPaths);

		return array_values(array_filter(array_map(function ($folder) use ($user, $loader, $conflicts, $aclManager, $rootRules) {
			// check for existing files in the user home and rename them if needed
			$originalFolderName = $folder['mount_point'];
			if (in_array($originalFolderName, $conflicts)) {
				/** @var IStorage $userStorage */
				$userStorage = $this->mountProviderCollection->getHomeMountForUser($user)->getStorage();
				$userCache = $userStorage->getCache();
				$i = 1;
				$folderName = $folder['mount_point'] . ' (' . $i++ . ')';

				while ($userCache->inCache("files/$folderName")) {
					$folderName = $originalFolderName . ' (' . $i++ . ')';
				}

				$userStorage->rename("files/$originalFolderName", "files/$folderName");
				$userCache->move("files/$originalFolderName", "files/$folderName");
				$userStorage->getPropagator()->propagateChange("files/$folderName", time());
			}

			return $this->getMount(
				$folder['folder_id'],
				'/' . $user->getUID() . '/files/' . $folder['mount_point'],
				$folder['permissions'],
				$folder['quota'],
				$folder['rootCacheEntry'],
				$loader,
				$folder['acl'],
				$user,
				$aclManager,
				$rootRules
			);
		}, $folders)));
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
		?IStorageFactory $loader = null,
		bool $acl = false,
		?IUser $user = null,
		?ACLManager $aclManager = null,
		array $rootRules = []
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

		$storage->setOwner($user?->getUID());

		$rootPath = $this->getJailPath($id);

		// apply acl before jail
		if ($acl && $user) {
			$inShare = $this->getCurrentUID() === null || $this->getCurrentUID() !== $user->getUID();
			$aclManager ??= $this->aclManagerFactory->getACLManager($user, $this->getRootStorageId());
			$aclRootPermissions = $aclManager->getPermissionsForPathFromRules($rootPath, $rootRules);
			$storage = new ACLStorageWrapper([
				'storage' => $storage,
				'acl_manager' => $aclManager,
				'in_share' => $inShare,
			]);
			$cacheEntry['permissions'] &= $aclRootPermissions;
		}

		if ($this->enableEncryption) {
			$baseStorage = new GroupFolderEncryptionJail([
				'storage' => $storage,
				'root' => $rootPath
			]);
			$quotaStorage = new GroupFolderStorage([
				'storage' => $baseStorage,
				'quota' => $quota,
				'folder_id' => $id,
				'rootCacheEntry' => $cacheEntry,
				'userSession' => $this->userSession,
				'mountOwner' => $user,
			]);
		} else {
			$baseStorage = new Jail([
				'storage' => $storage,
				'root' => $rootPath
			]);
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
			'mask' => $permissions
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
	private function findConflictsForUser(IUser $user, array $mountPoints): array {
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
}
