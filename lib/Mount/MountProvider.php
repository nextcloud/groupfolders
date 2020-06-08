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
use OCP\Files\Config\IMountProvider;
use OCP\Files\Folder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorageFactory;
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

	public function __construct(
		IGroupManager $groupProvider,
		FolderManager $folderManager,
		callable $rootProvider,
		ACLManagerFactory $aclManagerFactory,
		IUserSession $userSession,
		IRequest $request,
		ISession $session
	) {
		$this->groupProvider = $groupProvider;
		$this->folderManager = $folderManager;
		$this->rootProvider = $rootProvider;
		$this->aclManagerFactory = $aclManagerFactory;
		$this->userSession = $userSession;
		$this->request = $request;
		$this->session = $session;
	}

	public function getFoldersForUser(IUser $user) {
		return $this->folderManager->getFoldersForUser($user, $this->getRootFolder()->getStorage()->getCache()->getNumericStorageId());
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		return array_map(function ($folder) use ($user, $loader) {
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
		}, $folders);
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

	public function getMount($id, $mountPoint, $permissions, $quota, $cacheEntry = null, IStorageFactory $loader = null, bool $acl = false, IUser $user = null): IMountPoint {
		if (!$cacheEntry) {
			// trigger folder creation
			$this->getFolder($id);
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
				return $this->getRootFolder()->newFolder($id);
			} else {
				return null;
			}
		}
	}
}
