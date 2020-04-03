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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Folder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
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

	public function __construct(
		IGroupManager $groupProvider,
		FolderManager $folderManager,
		callable $rootProvider,
		ACLManagerFactory $aclManagerFactory,
		IUserSession $userSession,
		IRequest $request,
		ISession $session,
		IMountProviderCollection $mountProviderCollection,
		IDBConnection $connection
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
	}

	public function getFoldersForUser(IUser $user) {
		return $this->folderManager->getFoldersForUser($user, $this->getRootFolder()->getStorage()->getCache()->getNumericStorageId());
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		$mountPoints = array_map(function(array $folder) {
			return 'files/' . $folder['mount_point'];
		}, $folders);
		$conflicts = $this->findConflictsForUser($user, $mountPoints);

		return array_values(array_filter(array_map(function ($folder) use ($user, $loader, $conflicts) {
			// check for existing files in the user home and rename them if needed
			$originalFolderName = $folder['mount_point'];
			if (in_array($originalFolderName, $conflicts)) {
				/** @var IStorage $userStorage */
				$userStorage = $this->mountProviderCollection->getHomeMountForUser($user)->getStorage();
				$userCache = $userStorage->getCache();
				$i = 1;
				$folderName = $folder['mount_point'] . ' (' . $i++ . ')';

				while($userCache->inCache("files/$folderName")) {
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
				$user
			);
		}, $folders)));
	}

	private function getCurrentUID() {
		try {
			// wopi requests are not logged in, instead we need to get the editor user from the access token
			if (strpos($this->request->getRawPathInfo(), 'apps/richdocuments/wopi') && class_exists('OCA\Richdocuments\Db\WopiMapper')) {
				$wopiMapper = \OC::$server->query('OCA\Richdocuments\Db\WopiMapper');
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

	public function getMount($id, $mountPoint, $permissions, $quota, $cacheEntry = null, IStorageFactory $loader = null, bool $acl = false, IUser $user = null): ?IMountPoint {
		if (!$cacheEntry) {
			// trigger folder creation
			$folder = $this->getFolder($id);
			if ($folder === null) {
				return null;
			}
			$cacheEntry = $this->getRootFolder()->getStorage()->getCache()->get($folder->getId());
		}

		$storage = $this->getRootFolder()->getStorage();

		$rootPath = $this->getJailPath((int)$id);

		// apply acl before jail
		if ($acl && $user) {
			$inShare = $this->getCurrentUID() === null || $this->getCurrentUID() !== $user->getUID();
			$aclManager = $this->aclManagerFactory->getACLManager($user);
			$storage = new ACLStorageWrapper([
				'storage' => $storage,
				'acl_manager' => $aclManager,
				'in_share' => $inShare
			]);
			$aclRootPermissions = $aclManager->getACLPermissionsForPath($rootPath);
			$cacheEntry['permissions'] &= $aclRootPermissions;
		}

		$baseStorage = new Jail([
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
		$maskedStore = new PermissionsMask([
			'storage' => $quotaStorage,
			'mask' => $permissions
		]);

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

	public function getFolder($id, $create = true) {
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

	private function findConflictsForUser(IUser $user, array $mountPoints) {
		$userHome = $this->mountProviderCollection->getHomeMountForUser($user);

		$pathHashes = array_map('md5', $mountPoints);

		$query = $this->connection->getQueryBuilder();
		$query->select('path')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createNamedParameter($userHome->getNumericStorageId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->in('path_hash', $query->createNamedParameter($pathHashes, IQueryBuilder::PARAM_STR_ARRAY)));

		$paths = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
		return array_map(function($path) {
			return substr($path, 6); // strip leading "files/"
		}, $paths);
	}
}
