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
use OC\Files\Storage\Wrapper\Quota;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Folder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IGroupManager;
use OCP\IUser;

class MountProvider implements IMountProvider {
	/** @var IGroupManager */
	private $groupProvider;

	/** @var callable */
	private $rootProvider;

	/** @var Folder|null */
	private $root = null;

	/** @var FolderManager */
	private $folderManager;

	/**
	 * @param IGroupManager $groupProvider
	 * @param FolderManager $folderManager
	 * @param callable $rootProvider
	 */
	public function __construct(IGroupManager $groupProvider, FolderManager $folderManager, $rootProvider) {
		$this->groupProvider = $groupProvider;
		$this->folderManager = $folderManager;
		$this->rootProvider = $rootProvider;
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$groups = $this->groupProvider->getUserGroupIds($user);
		$folders = array_reduce($groups, function ($folders, $groupId) {
			return array_merge($folders, $this->folderManager->getFoldersForGroup($groupId));
		}, []);

		return array_map(function ($folder) use ($user, $loader) {
			return $this->getMount($folder['folder_id'], '/' . $user->getUID() . '/files/' . $folder['mount_point'], $folder['permissions'], $folder['quota'], $loader);
		}, $folders);
	}

	/**
	 * @param $id
	 * @param $mountPoint
	 * @param $permissions
	 * @return IMountPoint
	 */
	private function getMount($id, $mountPoint, $permissions, $quota, IStorageFactory $loader) {
		$folder = $this->getFolder($id);
		$baseStorage = new Jail([
			'storage' => $folder->getStorage(),
			'root' => $folder->getInternalPath()
		]);
		$maskedStore = new PermissionsMask([
			'storage' => $baseStorage,
			'mask' => $permissions
		]);
		$quotaStorage = new Quota([
			'storage' => $maskedStore,
			'quota' => $quota
		]);

		return new GroupMountPoint(
			$quotaStorage,
			$mountPoint,
			null,
			$loader
		);
	}

	public function getFolder($id, $create = true) {
		if (is_null($this->root)) {
			$rootProvider = $this->rootProvider;
			$this->root = $rootProvider();
		}
		try {
			return $this->root->get($id);
		} catch (NotFoundException $e) {
			if ($create) {
				return $this->root->newFolder($id);
			} else {
				return null;
			}
		}
	}
}
