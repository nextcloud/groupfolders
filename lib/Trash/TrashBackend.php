<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\Trash;

use OC\Files\Storage\Wrapper\Jail;
use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\ITrashItem;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

class TrashBackend implements ITrashBackend {
	/** @var FolderManager */
	private $folderManager;

	/** @var TrashManager */
	private $trashManager;

	/** @var Folder */
	private $appFolder;

	/** @var MountProvider */
	private $mountProvider;

	public function __construct(
		FolderManager $folderManager,
		TrashManager $trashManager,
		Folder $appFolder,
		MountProvider $mountProvider
	) {
		$this->folderManager = $folderManager;
		$this->trashManager = $trashManager;
		$this->appFolder = $appFolder;
		$this->mountProvider = $mountProvider;
	}

	public function listTrashRoot(IUser $user): array {
		$folders = $this->folderManager->getFoldersForUser($user);
		return $this->getTrashForFolders($user, array_map(function (array $folder) {
			return $folder['folder_id'];
		}, $folders));
	}

	public function listTrashFolder(ITrashItem $trashItem): array {
		$user = $trashItem->getUser();
		$folder = $this->getNodeForTrashItem($user, $trashItem);
		if (!$folder instanceof Folder) {
			return [];
		}
		$content = $folder->getDirectoryListing();
		return array_map(function (Node $node) use ($trashItem, $user) {
			return new GroupTrashItem(
				$this,
				$trashItem->getOriginalLocation() . '/' . $node->getName(),
				$trashItem->getDeletedTime(),
				$trashItem->getTrashPath() . '/' . $node->getName(),
				$node,
				$user
			);
		}, $content);
	}

	public function restoreItem(ITrashItem $item) {
		$user = $item->getUser();
		list(, $folderId) = explode('/', $item->getTrashPath());
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}

		$trashStorage = $node->getStorage();
		$targetFolder = $this->mountProvider->getFolder($folderId);
		$originalLocation = $item->getOriginalLocation();
		$parent = dirname($originalLocation);
		if ($parent === '.') {
			$parent = '';
		}
		if ($parent !== '' && !$targetFolder->nodeExists($parent)) {
			$originalLocation = basename($originalLocation);
		}
		$targetLocation = $targetFolder->getInternalPath() . '/' . $originalLocation;
		$targetFolder->getStorage()->moveFromStorage($trashStorage, $node->getInternalPath(), $targetLocation);
		$targetFolder->getStorage()->getCache()->moveFromCache($trashStorage->getCache(), $node->getInternalPath(), $targetLocation);
		$this->trashManager->removeItem($folderId, $item->getName(), $item->getDeletedTime());
	}

	public function removeItem(ITrashItem $item) {
		$user = $item->getUser();
		list(, $folderId) = explode('/', $item->getTrashPath());
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}
		$node->getStorage()->unlink($node->getInternalPath());
		$node->getStorage()->getCache()->remove($node->getInternalPath());
		if ($item->isRootItem()) {
			$this->trashManager->removeItem($folderId, $item->getName(), $item->getDeletedTime());
		}
	}

	public function moveToTrash(IStorage $storage, string $internalPath): bool {
		if ($storage->instanceOfStorage(GroupFolderStorage::class)) {
			/** @var GroupFolderStorage|Jail $storage */
			$name = basename($internalPath);
			$folderId = $storage->getFolderId();
			$trashFolder = $this->getTrashFolder($folderId);
			$trashStorage = $trashFolder->getStorage();
			$time = time();
			$trashName = $name . '.d' . $time;
			list($unJailedStorage, $unJailedInternalPath) = $this->unwrapJails($storage, $internalPath);
			$targetInternalPath = $trashFolder->getInternalPath() . '/' . $trashName;
			if ($trashStorage->moveFromStorage($unJailedStorage, $unJailedInternalPath, $targetInternalPath)) {
				$this->trashManager->addTrashItem($folderId, $name, $time, $internalPath);
				$trashStorage->getCache()->moveFromCache($unJailedStorage->getCache(), $unJailedInternalPath, $targetInternalPath);
			} else {
				throw new \Exception("Failed to move groupfolder item to trash");
			}
			return true;
		} else {
			return false;
		}
	}

	private function unwrapJails(IStorage $storage, string $internalPath): array {
		$unJailedInternalPath = $internalPath;
		$unJailedStorage = $storage;
		while ($unJailedStorage->instanceOfStorage(Jail::class)) {
			$unJailedStorage = $unJailedStorage->getWrapperStorage();
			if ($unJailedStorage instanceof Jail) {
				$unJailedInternalPath = $unJailedStorage->getUnjailedPath($unJailedInternalPath);
			}
		}
		return [$unJailedStorage, $unJailedInternalPath];
	}

	private function userHasAccessToFolder(IUser $user, int $folderId) {
		$folders = $this->folderManager->getFoldersForUser($user);
		$folderIds = array_map(function (array $folder) {
			return $folder['folder_id'];
		}, $folders);
		return in_array($folderId, $folderIds);
	}

	/**
	 * @param IUser $user
	 * @param ITrashItem $trashItem
	 * @return null|Node
	 */
	private function getNodeForTrashItem(IUser $user, ITrashItem $trashItem) {
		list(, $folderId, $path) = explode('/', $trashItem->getTrashPath(), 3);
		$folders = $this->folderManager->getFoldersForUser($user);
		foreach ($folders as $groupFolder) {
			if ($groupFolder['folder_id'] === (int)$folderId) {
				$trashRoot = $this->getTrashFolder($folderId);
				try {
					$node = $trashRoot->get($path);
					return $node;
				} catch (NotFoundException $e) {
					return null;
				}
			}
		}
		return null;
	}

	private function getTrashFolder($folderId) {
		try {
			return $this->appFolder->get('trash/' . $folderId);
		} catch (NotFoundException $e) {
			/** @var Folder $trashRoot */
			$trashRoot = $this->appFolder->nodeExists('trash') ? $this->appFolder->get('trash') : $this->appFolder->newFolder('trash');
			return $trashRoot->newFolder($folderId);
		}
	}

	private function getTrashForFolders(IUser $user, array $folderIds) {
		$rows = $this->trashManager->listTrashForFolders($folderIds);
		$indexedRows = [];
		foreach ($rows as $row) {
			$key = $row['folder_id'] . '/' . $row['name'] . '/' . $row['deleted_time'];
			$indexedRows[$key] = $row;
		}
		$items = [];
		foreach ($folderIds as $folderId) {
			$trashFolder = $this->getTrashFolder($folderId);
			$content = $trashFolder->getDirectoryListing();
			foreach ($content as $item) {
				$pathParts = pathinfo($item->getName());
				$timestamp = substr($pathParts['extension'], 1);
				$name = $pathParts['filename'];
				$key = $folderId . '/' . $name . '/' . $timestamp;
				$originalLocation = isset($indexedRows[$key]) ? $indexedRows[$key]['original_location'] : '';
				$info = $item->getFileInfo();
				$info['name'] = $name;
				$items[] = new GroupTrashItem(
					$this,
					$originalLocation,
					$timestamp,
					'/' . $folderId . '/' . $item->getName(),
					$info,
					$user
				);
			}
		}
		return $items;
	}

	public function getTrashNodeById(IUser $user, int $fileId) {
		try {
			/** @var Folder $trashFolder */
			$trashFolder = $this->appFolder->get('trash');
			$storage = $this->appFolder->getStorage();
			$path = $storage->getCache()->getPathById($fileId);
			if (!$path) {
				return null;
			}
			$absolutePath = $this->appFolder->getMountPoint()->getMountPoint() . $path;
			$relativePath = $trashFolder->getRelativePath($absolutePath);
			list(, $folderId) = explode('/', $relativePath);
			if ($this->userHasAccessToFolder($user, (int)$folderId)) {
				return $trashFolder->get($relativePath);
			} else {
				return null;
			}
		} catch (NotFoundException $e) {
			return null;
		}
	}
}
