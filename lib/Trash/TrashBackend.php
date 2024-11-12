<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OC\Files\Storage\Wrapper\Jail;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\ITrashItem;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type InternalFolder from FolderManager
 */
class TrashBackend implements ITrashBackend {
	/** @var ?VersionsBackend */
	private $versionsBackend = null;

	public function __construct(
		private FolderManager $folderManager,
		private TrashManager $trashManager,
		private Folder $appFolder,
		private MountProvider $mountProvider,
		private ACLManagerFactory $aclManagerFactory,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		private IUserSession $userSession,
	) {
	}

	public function setVersionsBackend(VersionsBackend $versionsBackend): void {
		$this->versionsBackend = $versionsBackend;
	}

	/**
	 * @return list<ITrashItem>
	 */
	public function listTrashRoot(IUser $user): array {
		$folders = $this->folderManager->getFoldersForUser($user);
		return $this->getTrashForFolders($user, $folders);
	}

	/**
	 * @return list<ITrashItem>
	 */
	public function listTrashFolder(ITrashItem $trashItem): array {
		if (!$trashItem instanceof GroupTrashItem) {
			return [];
		}
		$user = $trashItem->getUser();
		$folder = $this->getNodeForTrashItem($user, $trashItem);
		if (!$folder instanceof Folder) {
			return [];
		}
		$content = $folder->getDirectoryListing();
		$this->aclManagerFactory->getACLManager($user)->preloadRulesForFolder($trashItem->getPath());
		return array_values(array_filter(array_map(function (Node $node) use ($trashItem, $user) {
			if (!$this->userHasAccessToPath($user, $trashItem->getPath() . '/' . $node->getName())) {
				return null;
			}
			return new GroupTrashItem(
				$this,
				$trashItem->getOriginalLocation() . '/' . $node->getName(),
				$trashItem->getDeletedTime(),
				$trashItem->getTrashPath() . '/' . $node->getName(),
				$node,
				$user,
				$trashItem->getGroupFolderMountPoint(),
				$trashItem->getDeletedBy(),
			);
		}, $content)));
	}

	/**
	 * @return void
	 * @throw NotPermittedException
	 */
	public function restoreItem(ITrashItem $item) {
		if (!($item instanceof GroupTrashItem)) {
			throw new \LogicException('Trying to restore normal trash item in group folder trash backend');
		}
		$user = $item->getUser();
		[, $folderId] = explode('/', $item->getTrashPath());
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}
		if (!$this->userHasAccessToPath($item->getUser(), $item->getPath(), Constants::PERMISSION_UPDATE)) {
			throw new NotPermittedException();
		}
		$folderPermissions = $this->folderManager->getFolderPermissionsForUser($item->getUser(), (int)$folderId);
		if (($folderPermissions & Constants::PERMISSION_UPDATE) !== Constants::PERMISSION_UPDATE) {
			throw new NotPermittedException();
		}

		$trashStorage = $node->getStorage();
		/** @var Folder $targetFolder */
		$targetFolder = $this->mountProvider->getFolder((int)$folderId);
		$originalLocation = $item->getOriginalLocation();
		$parent = dirname($originalLocation);
		if ($parent === '.') {
			$parent = '';
		}
		if ($parent !== '' && !$targetFolder->nodeExists($parent)) {
			$originalLocation = basename($originalLocation);
		}

		if ($targetFolder->nodeExists($originalLocation)) {
			$info = pathinfo($originalLocation);
			$i = 1;

			$gen = function ($info, int $i): string {
				$target = $info['dirname'];
				if ($target === '.') {
					$target = '';
				}

				$target .= $info['filename'];
				$target .= ' (' . $i . ')';

				if (isset($info['extension'])) {
					$target .= $info['extension'];
				}

				return $target;
			};

			do {
				$originalLocation = $gen($info, $i);
				$i++;
			} while ($targetFolder->nodeExists($originalLocation));
		}

		$targetLocation = $targetFolder->getInternalPath() . '/' . $originalLocation;
		$targetFolder->getStorage()->moveFromStorage($trashStorage, $node->getInternalPath(), $targetLocation);
		$targetFolder->getStorage()->getUpdater()->renameFromStorage($trashStorage, $node->getInternalPath(), $targetLocation);
		$this->trashManager->removeItem((int)$folderId, $item->getName(), $item->getDeletedTime());
		\OCP\Util::emitHook(
			'\OCA\Files_Trashbin\Trashbin',
			'post_restore',
			[
				'filePath' => '/' . $item->getGroupFolderMountPoint() . '/' . $originalLocation,
				'trashPath' => $item->getPath(),
			]
		);
	}

	/**
	 * @return void
	 * @throw \LogicException
	 * @throw \Exception
	 */
	public function removeItem(ITrashItem $item) {
		if (!($item instanceof GroupTrashItem)) {
			throw new \LogicException('Trying to remove normal trash item in group folder trash backend');
		}
		$user = $item->getUser();
		[, $folderId] = explode('/', $item->getTrashPath());
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}

		if (!$this->userHasAccessToPath($item->getUser(), $item->getPath(), Constants::PERMISSION_DELETE)) {
			throw new NotPermittedException();
		}

		$folderPermissions = $this->folderManager->getFolderPermissionsForUser($item->getUser(), (int)$folderId);
		if (($folderPermissions & Constants::PERMISSION_DELETE) !== Constants::PERMISSION_DELETE) {
			throw new NotPermittedException();
		}

		if ($node->getStorage()->unlink($node->getInternalPath()) === false) {
			throw new \Exception('Failed to remove item from trashbin');
		}

		$node->getStorage()->getCache()->remove($node->getInternalPath());
		if ($item->isRootItem()) {
			$this->trashManager->removeItem((int)$folderId, $item->getName(), $item->getDeletedTime());
		}
	}

	public function moveToTrash(IStorage $storage, string $internalPath): bool {
		if ($storage->instanceOfStorage(GroupFolderStorage::class) && $storage->isDeletable($internalPath)) {
			/** @var GroupFolderStorage|Jail $storage */
			$name = basename($internalPath);
			$fileEntry = $storage->getCache()->get($internalPath);
			$folderId = $storage->getFolderId();
			$trashFolder = $this->getTrashFolder($folderId);
			$trashStorage = $trashFolder->getStorage();
			$time = time();
			$trashName = $name . '.d' . $time;
			[$unJailedStorage, $unJailedInternalPath] = $this->unwrapJails($storage, $internalPath);
			$targetInternalPath = $trashFolder->getInternalPath() . '/' . $trashName;
			if ($trashStorage->moveFromStorage($unJailedStorage, $unJailedInternalPath, $targetInternalPath)) {
				$this->trashManager->addTrashItem($folderId, $name, $time, $internalPath, $fileEntry->getId(), $this->userSession->getUser()->getUID());
				if ($trashStorage->getCache()->getId($targetInternalPath) !== $fileEntry->getId()) {
					$trashStorage->getCache()->moveFromCache($unJailedStorage->getCache(), $unJailedInternalPath, $targetInternalPath);
				}
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

	private function userHasAccessToFolder(IUser $user, int $folderId): bool {
		$folders = $this->folderManager->getFoldersForUser($user);
		$folderIds = array_map(function (array $folder): int {
			return $folder['folder_id'];
		}, $folders);
		return in_array($folderId, $folderIds);
	}

	private function userHasAccessToPath(
		IUser $user,
		string $path,
		int $permission = Constants::PERMISSION_READ
	): bool {
		$activePermissions = $this->aclManagerFactory->getACLManager($user)
			->getACLPermissionsForPath($path);
		return (bool)($activePermissions & $permission);
	}

	private function getNodeForTrashItem(IUser $user, ITrashItem $trashItem): ?Node {
		[, $folderId, $path] = explode('/', $trashItem->getTrashPath(), 3);
		$folders = $this->folderManager->getFoldersForUser($user);
		foreach ($folders as $groupFolder) {
			if ($groupFolder['folder_id'] === (int)$folderId) {
				$trashRoot = $this->getTrashFolder((int)$folderId);
				try {
					$node = $trashRoot->get($path);
					if (!$this->userHasAccessToPath($user, $trashItem->getPath())) {
						return null;
					}
					return $node;
				} catch (NotFoundException $e) {
					return null;
				}
			}
		}
		return null;
	}

	private function getTrashRoot(): Folder {
		try {
			return $this->appFolder->get('trash');
		} catch (NotFoundException $e) {
			return $this->appFolder->newFolder('trash');
			;
		}
	}

	private function getTrashFolder(int $folderId): Folder {
		try {
			return $this->appFolder->get('trash/' . $folderId);
		} catch (NotFoundException $e) {
			/** @var Folder $trashRoot */
			$trashRoot = $this->appFolder->nodeExists('trash') ? $this->appFolder->get('trash') : $this->appFolder->newFolder('trash');
			return $trashRoot->newFolder((string)$folderId);
		}
	}

	/**
	 * @param list<InternalFolder> $folders
	 * @return list<ITrashItem>
	 */
	private function getTrashForFolders(IUser $user, array $folders): array {
		$folderIds = array_map(function (array $folder): int {
			return $folder['folder_id'];
		}, $folders);
		$rows = $this->trashManager->listTrashForFolders($folderIds);
		$indexedRows = [];
		$trashItemsByOriginalLocation = [];
		foreach ($rows as $row) {
			$key = $row['folder_id'] . '/' . $row['name'] . '/' . $row['deleted_time'];
			$indexedRows[$key] = $row;
			$trashItemsByOriginalLocation[$row['original_location']] = $row;
		}
		$items = [];
		foreach ($folders as $folder) {
			$folderId = $folder['folder_id'];
			$folderHasAcl = $folder['acl'];
			$mountPoint = $folder['mount_point'];
			$trashFolder = $this->getTrashFolder($folderId);
			$content = $trashFolder->getDirectoryListing();
			$userCanManageAcl = $this->folderManager->canManageACL($folderId, $user);
			$this->aclManagerFactory->getACLManager($user)->preloadRulesForFolder($trashFolder->getPath());
			foreach ($content as $item) {
				/** @var \OC\Files\Node\Node $item */
				$pathParts = pathinfo($item->getName());
				$timestamp = (int)substr($pathParts['extension'], 1);
				$name = $pathParts['filename'];
				$key = $folderId . '/' . $name . '/' . $timestamp;

				$originalLocation = isset($indexedRows[$key]) ? $indexedRows[$key]['original_location'] : '';
				$deletedBy = isset($indexedRows[$key]) ? $indexedRows[$key]['deleted_by'] : '';

				if ($folderHasAcl) {
					// if we for any reason lost track of the original location, hide the item for non-managers as a fail-safe
					if ($originalLocation === '' && !$userCanManageAcl) {
						continue;
					}
					if (!$this->userHasAccessToPath($user, $item->getPath())) {
						continue;
					}
					// if a parent of the original location has also been deleted, we also need to check it based on the now-deleted parent path
					foreach ($this->getParentOriginalPaths($originalLocation, $trashItemsByOriginalLocation) as $parentOriginalPath) {
						$parentTrashItem = $trashItemsByOriginalLocation[$parentOriginalPath];
						$relativePath = substr($originalLocation, strlen($parentOriginalPath));
						$parentTrashItemPath = "__groupfolders/trash/{$parentTrashItem['folder_id']}/{$parentTrashItem['name']}.d{$parentTrashItem['deleted_time']}";
						if (!$this->userHasAccessToPath($user, $parentTrashItemPath . $relativePath)) {
							continue 2;
						}
					}
				}

				$info = $item->getFileInfo();
				$info['name'] = $name;
				$items[] = new GroupTrashItem(
					$this,
					$originalLocation,
					$timestamp,
					'/' . $folderId . '/' . $item->getName(),
					$info,
					$user,
					$mountPoint,
					$this->userManager->get($deletedBy),
				);
			}
		}
		return $items;
	}

	private function getParentOriginalPaths(string $path, array $trashItemsByOriginalPath): array {
		$parentPaths = [];
		while ($path !== '') {
			$path = dirname($path);

			if ($path === '.' || $path === '/') {
				break;
			} elseif (isset($trashItemsByOriginalPath[$path])) {
				$parentPaths[] = $path;
			}
		}
		return $parentPaths;
	}

	public function getTrashNodeById(IUser $user, int $fileId): ?Node {
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
			[, $folderId, $nameAndTime] = explode('/', $relativePath);

			if ($this->userHasAccessToFolder($user, (int)$folderId) && $this->userHasAccessToPath($user, $absolutePath)) {
				return $trashFolder->get($relativePath);
			} else {
				return null;
			}
		} catch (NotFoundException $e) {
			return null;
		}
	}

	public function cleanTrashFolder(int $folderid): void {
		$trashFolder = $this->getTrashFolder($folderid);

		if (!($trashFolder instanceof Folder)) {
			return;
		}

		foreach ($trashFolder->getDirectoryListing() as $node) {
			$node->delete();
		}

		$this->trashManager->emptyTrashbin($folderid);
	}

	public function expire(Expiration $expiration): array {
		$size = 0;
		$count = 0;
		$folders = $this->folderManager->getAllFoldersWithSize($this->rootFolder->getMountPoint()->getNumericStorageId());
		foreach ($folders as $folder) {
			$folderId = $folder['id'];
			$trashItems = $this->trashManager->listTrashForFolders([$folderId]);

			// calculate size of trash items
			$sizeInTrash = 0;
			$trashFolder = $this->getTrashFolder($folderId);
			$nodes = []; // cache
			foreach ($trashItems as $groupTrashItem) {
				$nodeName = $groupTrashItem['name'] . '.d' . $groupTrashItem['deleted_time'];
				try {
					$nodes[$nodeName] = $node = $trashFolder->get($nodeName);
				} catch (NotFoundException $e) {
					$this->trashManager->removeItem($folderId, $groupTrashItem['name'], $groupTrashItem['deleted_time']);
					continue;
				}
				$sizeInTrash += $node->getSize();
			}
			foreach ($trashItems as $groupTrashItem) {
				$nodeName = $groupTrashItem['name'] . '.d' . $groupTrashItem['deleted_time'];
				if (!isset($nodes[$nodeName])) {
					continue;
				}
				$node = $nodes[$nodeName];

				if ($expiration->isExpired($groupTrashItem['deleted_time'], $folder['quota'] > 0 && $folder['quota'] < ($folder['size'] + $sizeInTrash))) {
					$this->logger->debug("expiring " . $node->getPath());
					if ($node->getStorage()->unlink($node->getInternalPath()) === false) {
						$this->logger->error("Failed to remove item from trashbin: " . $node->getPath());
						continue;
					}
					// only count up after checking if removal is possible
					$count += 1;
					$size += $node->getSize();
					$folder['size'] -= $node->getSize();
					$node->getStorage()->getCache()->remove($node->getInternalPath());
					$this->trashManager->removeItem($folderId, $groupTrashItem['name'], $groupTrashItem['deleted_time']);
					if (!is_null($groupTrashItem['file_id']) && !is_null($this->versionsBackend)) {
						$this->versionsBackend->deleteAllVersionsForFile($folderId, $groupTrashItem['file_id']);
					}
				} else {
					$this->logger->debug($node->getPath() . " isn't set to be expired yet, stopping expiry");
					break;
				}
			}
		}

		$this->cleanupDeletedFoldersTrash($folders);

		return [$count, $size];
	}

	/**
	 * Cleanup trashbin of of groupfolders that have been deleted
	 *
	 * @param array $existingFolders
	 * @return void
	 */
	private function cleanupDeletedFoldersTrash(array $existingFolders): void {
		$trashRoot = $this->getTrashRoot();
		foreach ($trashRoot->getDirectoryListing() as $trashFolder) {
			$folderId = $trashFolder->getName();
			if (is_numeric($folderId)) {
				$folderId = (int)$folderId;
				if (!isset($existingFolders[$folderId])) {
					$this->cleanTrashFolder($folderId);
					$this->getTrashFolder($folderId)->delete();
				}
			}
		}
	}
}
