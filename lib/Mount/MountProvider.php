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
use OCA\GroupFolders\ACL\ACLManager;
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
	private IGroupManager $groupProvider;
	private callable $rootProvider;
	private ?Folder $root = null;
	private FolderManager $folderManager;
	private ACLManagerFactory $aclManagerFactory;
	private IUserSession $userSession;
	private IRequest $request;
	private ISession $session;
	private IMountProviderCollection $mountProviderCollection;
	private IDBConnection $connection;
	private ICache $cache;
	private ?int $rootStorageId = null;
	private bool $allowRootShare;
	private bool $enableEncryption;

	public function __construct(
		private IGroupManager $groupProvider,
		private FolderManager $folderManager,
		private callable $rootProvider,
		private ACLManagerFactory $aclManagerFactory,
		private IUserSession $userSession,
		private IRequest $request,
		private ISession $session,
		private IMountProviderCollection $mountProviderCollection,
		private IDBConnection $connection,
		private ICache $cache,
		bool $allowRootShare,
		bool $enableEncryption
	) {
		$this->allowRootShare = $allowRootShare;
		$this->enableEncryption = $enableEncryption;
	}

	private function getRootStorageId(): int {
		if ($this->rootStorageId === null) {
			$cached = $this->cache->get("root_storage_id");
			$this->rootStorageId = $cached ??= $this->getRootFolder()?->getStorage()?->getCache()?->getNumericStorageId();
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

		$mountPoints = array_map(fn(array $folder) => 'files/' . $folder['mount_point'], $folders);
		$conflicts = $this->findConflictsForUser($user, $mountPoints);

		$foldersWithAcl = array_filter($folders, fn(array $folder) => $folder['acl']);
		$aclRootPaths = array_map(fn(array $folder) => $this->getJailPath($folder['folder_id']), $foldersWithAcl);

		$aclManager = $this->aclManagerFactory->getACLManager($user, $this->getRootStorageId());
		$aclManager->preloadPaths($aclRootPaths);

		array_walk($folders, function (&$folder) use ($user, $loader, $conflicts, $aclManager) {
			$originalFolderName = $folder['mount_point'];

			if (in_array($originalFolderName, $conflicts)) {
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

			$folder = $this->getMount(
				$folder['folder_id'],
				'/' . $user->getUID() . '/files/' . $originalFolderName,
				$folder['permissions'],
				$folder['quota'],
				$folder['rootCacheEntry'],
				$loader,
				$folder['acl'],
				$user,
				$aclManager
			);
		});

		return array_values(array_filter($folders));
	}

	private function getCurrentUID(): ?string {
		try {
			// WOPI requests are not logged in; instead, we need to get the editor user from the access token
			if (strpos($this->request->getRawPathInfo(), 'apps/richdocuments/wopi') && class_exists('OCA\Richdocuments\Db\WopiMapper')) {
				$wopiMapper = \OC::$server->get('OCA\Richdocuments\Db\WopiMapper');
				$token = $this->request->getParam('access_token');
				if ($token) {
					if ($wopi = $wopiMapper->getPathForToken($token)) {
						return $wopi->getEditorUid();
        				}
        			}
			}
		} catch (\Exception $e) {
		}

		$user = $this->userSession->getUser();
		return $user?->getUID() ?? null;
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
		?ACLManager $aclManager = null
	): ?IMountPoint {
		$cacheEntry ??= $this->getFolder($id) ? $this->getRootFolder()->getStorage()->getCache()->get($folder->getId()) : null;

		$storage = $this->getRootFolder()->getStorage();
		$rootPath = $this->getJailPath($id);

		if ($acl && $user) {
			$inShare = $this->getCurrentUID() === null || $this->getCurrentUID() !== $user->getUID();
			$aclManager ??= $this->aclManagerFactory->getACLManager($user, $this->getRootStorageId());

			$storage = new ACLStorageWrapper([
				'storage' => $storage,
				'acl_manager' => $aclManager,
				'in_share' => $inShare
			]);

			$aclRootPermissions = $aclManager?->getACLPermissionsForPath($rootPath) ?? 0;
			$cacheEntry['permissions'] &= $aclRootPermissions;
		}

		$baseStorage = new Jail([
			'storage' => $storage,
			'root' => $rootPath
		]);

		$quotaStorageClass = $this->enableEncryption ? GroupFolderStorage::class : GroupFolderNoEncryptionStorage::class;
		$quotaStorage = new $quotaStorageClass([
			'storage' => $baseStorage,
			'quota' => $quota,
			'folder_id' => $id,
			'rootCacheEntry' => $cacheEntry,
			'userSession' => $this->userSession,
			'mountOwner' => $user
		]);

		$maskedStore = new PermissionsMask([
			'storage' => $quotaStorage,
			'mask' => $permissions
		]);

		if (!$this->allowRootShare) {
			$maskedStore = new RootPermissionsMask([
				'storage' => $maskedStore,
				'mask' => Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE
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
		return $this->root ??= ($this->rootProvider)();
	}

	public function getFolder(int $id, bool $create = true): ?Node {
		try {
			return $this->getRootFolder()->get((string)$id);
		} catch (NotFoundException $e) {
			return $create ? $this->getRootFolder()->newFolder((string)$id) : null;
		}
	}

	/**
	 * @param string[] $mountPoints
	 * @return string[] An array of paths
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
			$paths = array_merge($paths, array_column($query->executeQuery()->fetchAllAssociative(), 'path'));
		}

		return array_map(fn(string $path) => substr($path, 6), $paths); // strip leading "files/"
	}
}
