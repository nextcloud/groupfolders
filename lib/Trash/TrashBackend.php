<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OC\Encryption\Exceptions\DecryptionFailedException;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Encryption;
use OC\Files\Storage\Wrapper\Jail;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Storage;
use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\ITrashItem;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\ISharedStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class TrashBackend implements ITrashBackend {
	private ?VersionsBackend $versionsBackend = null;

	public function __construct(
		private readonly FolderManager $folderManager,
		private readonly TrashManager $trashManager,
		private readonly ACLManagerFactory $aclManagerFactory,
		private readonly IRootFolder $rootFolder,
		private readonly LoggerInterface $logger,
		private readonly IUserManager $userManager,
		private readonly IUserSession $userSession,
		private readonly MountProvider $mountProvider,
		private readonly IMountManager $mountManager,
		private readonly IStorageFactory $storageFactory,
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
	public function listTrashFolder(ITrashItem $folder): array {
		if (!$folder instanceof GroupTrashItem) {
			return [];
		}

		$user = $folder->getUser();
		$folderNode = $this->getNodeForTrashItem($user, $folder);
		if (!$folderNode instanceof Folder) {
			return [];
		}

		$content = $folderNode->getDirectoryListing();
		$this->aclManagerFactory->getACLManager($user)->preloadRulesForFolder($folder->getGroupFolderStorageId(), $folder->getId());

		return array_values(array_filter(array_map(function (Node $node) use ($folder, $user): ?GroupTrashItem {
			$item = new GroupTrashItem(
				$this,
				$folder->getInternalOriginalLocation() . '/' . $node->getName(),
				$folder->getDeletedTime(),
				$folder->getTrashPath() . '/' . $node->getName(),
				$node,
				$user,
				$folder->getGroupFolderMountPoint(),
				$folder->getDeletedBy(),
				$folder->folder,
			);

			if (!$this->userHasAccessToItem($item)) {
				return null;
			}

			return $item;
		}, $content)));
	}

	/**
	 * @throws NotPermittedException
	 */
	public function restoreItem(ITrashItem $item): void {
		if (!($item instanceof GroupTrashItem)) {
			throw new \LogicException('Trying to restore normal trash item in Team folder trash backend');
		}

		$user = $item->getUser();
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$folderId = $item->folder->id;
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}

		if (!$this->userHasAccessToItem($item, Constants::PERMISSION_UPDATE)) {
			throw new NotPermittedException();
		}

		$folderPermissions = $this->folderManager->getFolderPermissionsForUser($item->getUser(), $folderId);
		if (($folderPermissions & Constants::PERMISSION_UPDATE) !== Constants::PERMISSION_UPDATE) {
			throw new NotPermittedException();
		}

		$trashStorage = $node->getStorage();
		/** @var Folder $targetFolder */
		$targetFolder = $userFolder->get($item->getGroupFolderMountPoint());
		$originalLocation = $item->getInternalOriginalLocation();
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

			$gen = function (array $info, int $i): string {
				$target = $info['dirname'];
				if ($target === '.') {
					$target = '';
				}

				$target .= $info['filename'];
				$target .= ' (' . $i . ')';

				if (isset($info['extension'])) {
					$target .= '.' . $info['extension'];
				}

				return $target;
			};

			do {
				$originalLocation = $gen($info, $i);
				$i++;
			} while ($targetFolder->nodeExists($originalLocation));
		}

		$targetLocation = $targetFolder->getInternalPath() . '/' . $originalLocation;
		$targetStorage = $targetFolder->getStorage();
		$trashLocation = $node->getInternalPath();
		try {
			$targetStorage->moveFromStorage($trashStorage, $trashLocation, $targetLocation);
			$targetStorage->getUpdater()->renameFromStorage($trashStorage, $trashLocation, $targetLocation);
		} catch (DecryptionFailedException) {
			// Before https://github.com/nextcloud/groupfolders/pull/3425 the key would be in the wrong place, leading to the decryption failure.
			// for those we fall back to the old restore behavior
			[$unwrappedTargetStorage, $unwrappedTargetLocation] = $this->unwrapJails($targetStorage, $targetLocation);
			[$unwrappedTrashStorage, $unwrappedTrashLocation] = $this->unwrapJails($trashStorage, $trashLocation);
			$unwrappedTargetStorage->moveFromStorage($unwrappedTrashStorage, $unwrappedTrashLocation, $unwrappedTargetLocation);
			$unwrappedTargetStorage->getUpdater()->renameFromStorage($unwrappedTrashStorage, $unwrappedTrashLocation, $unwrappedTargetLocation);
		}
		$this->trashManager->removeItem($folderId, $item->getName(), $item->getDeletedTime());
		\OCP\Util::emitHook(
			'\OCA\Files_Trashbin\Trashbin',
			'post_restore',
			[
				'filePath' => '/' . $item->getGroupFolderMountPoint() . '/' . $originalLocation,
				'trashPath' => $item->getPath(),
			],
		);
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

	/**
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function removeItem(ITrashItem $item): void {
		if (!($item instanceof GroupTrashItem)) {
			throw new \LogicException('Trying to remove normal trash item in Team folder trash backend');
		}

		$user = $item->getUser();
		$folderId = $item->folder->id;
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}

		if (!$this->userHasAccessToItem($item, Constants::PERMISSION_DELETE)) {
			throw new NotPermittedException();
		}

		$folderPermissions = $this->folderManager->getFolderPermissionsForUser($item->getUser(), $folderId);
		if (($folderPermissions & Constants::PERMISSION_DELETE) !== Constants::PERMISSION_DELETE) {
			throw new NotPermittedException();
		}

		if ($node->getStorage()->unlink($node->getInternalPath()) === false) {
			throw new \Exception('Failed to remove item from trashbin');
		}

		$node->getStorage()->getCache()->remove($node->getInternalPath());
		if ($item->isRootItem()) {
			$this->trashManager->removeItem($folderId, $item->getName(), $item->getDeletedTime());
		}
	}

	public function moveToTrash(IStorage $storage, string $internalPath): bool {
		if ($storage->instanceOfStorage(GroupFolderStorage::class) && $storage->isDeletable($internalPath)) {
			/** @var GroupFolderStorage $storage */
			$name = basename($internalPath);
			$fileEntry = $storage->getCache()->get($internalPath);
			$folder = $storage->getFolder();
			$folderId = $storage->getFolderId();

			$trashFolder = $this->setupTrashFolder($folder, $storage->getUser());
			$trashStorage = $trashFolder->getStorage();
			$time = time();
			$trashName = $name . '.d' . $time;
			$targetInternalPath = $trashFolder->getInternalPath() . '/' . $trashName;
			// until the fix from https://github.com/nextcloud/server/pull/49262 is in all versions we support we need to manually disable the optimization
			if ($storage->instanceOfStorage(Encryption::class)) {
				$result = $this->moveFromEncryptedStorage($storage, $trashStorage, $internalPath, $targetInternalPath);
			} else {
				$result = $trashStorage->moveFromStorage($storage, $internalPath, $targetInternalPath);
			}
			if ($result) {
				$originalLocation = $internalPath;
				if ($storage->instanceOfStorage(ISharedStorage::class)) {
					$originalLocation = $storage->getWrapperStorage()->getUnjailedPath($originalLocation);
				}

				$deletedBy = $this->userSession->getUser();
				$this->trashManager->addTrashItem($folderId, $name, $time, $originalLocation, $fileEntry->getId(), $deletedBy?->getUID() ?? '');

				// some storage backends (object/encryption) can either already move the cache item or cause the target to be scanned
				// so we only conditionally do the cache move here
				if (!$trashStorage->getCache()->inCache($targetInternalPath)) {
					// doesn't exist in target yet, do the move
					$trashStorage->getCache()->moveFromCache($storage->getCache(), $internalPath, $targetInternalPath);
				} elseif ($storage->getCache()->inCache($internalPath)) {
					// exists in both source and target, cleanup source
					$storage->getCache()->remove($internalPath);
				}
			} else {
				throw new \Exception('Failed to move Team folder item to trash');
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * move from storage when we can't just move within the storage
	 *
	 * This is copied from the fallback implementation from Common::moveFromStorage
	 */
	private function moveFromEncryptedStorage(IStorage $sourceStorage, IStorage $targetStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		if (!$sourceStorage->isDeletable($sourceInternalPath)) {
			return false;
		}

		// the trash should be the top wrapper, remove it to prevent recursive attempts to move to trash
		if ($sourceStorage instanceof Storage) {
			$sourceStorage = $sourceStorage->getWrapperStorage();
		}

		$result = $targetStorage->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath, true);
		if ($result) {
			if ($sourceStorage->instanceOfStorage(ObjectStoreStorage::class)) {
				/** @var ObjectStoreStorage $sourceStorage */
				$sourceStorage->setPreserveCacheOnDelete(true);
			}
			try {
				if ($sourceStorage->is_dir($sourceInternalPath)) {
					$result = $sourceStorage->rmdir($sourceInternalPath);
				} else {
					$result = $sourceStorage->unlink($sourceInternalPath);
				}
			} finally {
				if ($sourceStorage->instanceOfStorage(ObjectStoreStorage::class)) {
					/** @var ObjectStoreStorage $sourceStorage */
					$sourceStorage->setPreserveCacheOnDelete(false);
				}
			}
		}
		return $result;
	}

	private function userHasAccessToFolder(IUser $user, int $folderId): bool {
		return $this->folderManager->getFoldersForUser($user, $folderId) !== [];
	}

	private function userHasAccessToItem(
		GroupTrashItem $item,
		int $permission = Constants::PERMISSION_READ,
		string $pathInsideItem = '',
	): bool {
		try {
			$aclManager = $this->aclManagerFactory->getACLManager($item->getUser());
			$trashPath = $this->getUnJailedPath($item->getTrashNode()) . $pathInsideItem;
			$activePermissions = $aclManager->getACLPermissionsForPath($item->folder->id, $item->getGroupTrashFolderStorageId(), $trashPath);
			$originalPath = $item->folder->rootCacheEntry->getPath() . '/' . $item->getInternalOriginalLocation() . $pathInsideItem;
			$originalLocationPermissions = $aclManager->getACLPermissionsForPath($item->folder->id, $item->getGroupFolderStorageId(), $originalPath);
		} catch (\Exception $e) {
			$this->logger->warning("Failed to get permissions for {$item->getPath()}", ['exception' => $e]);
			return false;
		}

		return (bool)($activePermissions & $permission & $originalLocationPermissions);
	}

	private function getNodeForTrashItem(IUser $user, ITrashItem $trashItem): ?Node {
		if (!($trashItem instanceof GroupTrashItem)) {
			throw new \LogicException('Trying to remove normal trash item in Team folder trash backend');
		}

		$folderId = $trashItem->folder->id;
		$path = $trashItem->getFullInternalPath();
		$folders = $this->folderManager->getFoldersForUser($user, $folderId);
		foreach ($folders as $groupFolder) {
			if ($groupFolder->id === $folderId) {
				$trashRoot = $this->setupTrashFolder($groupFolder, $user);
				try {
					$node = $trashRoot->get($path);
					if (!$this->userHasAccessToItem($trashItem)) {
						return null;
					}

					return $node;
				} catch (NotFoundException) {
					return null;
				}
			}
		}

		return null;
	}

	private function setupTrashFolder(FolderDefinitionWithPermissions $folder, ?IUser $user = null): Folder {
		$folderId = $folder->id;

		$uid = $user ? $user->getUID() : 'dummy';

		$mountPoint = '/' . $uid . '/files_trashbin/groupfolders/' . $folderId;
		$mount = $this->mountManager->find($mountPoint);
		if ($mount->getMountPoint() !== $mountPoint) {
			$trashMount = $this->mountProvider->getTrashMount(
				$folder,
				$mountPoint,
				$this->storageFactory,
				$user,
			);
			$this->mountManager->addMount($trashMount);
		}

		return $this->rootFolder->get('/' . $uid . '/files_trashbin/groupfolders/' . $folderId);
	}

	private function getUnJailedPath(Node $node): string {
		$storage = $node->getStorage();
		$path = $node->getInternalPath();
		while ($storage->instanceOfStorage(Jail::class)) {
			/** @var Jail $storage */
			$path = $storage->getUnjailedPath($path);
			$storage = $storage->getUnjailedStorage();
		}
		return $path;
	}

	/**
	 * @param list<FolderDefinitionWithPermissions> $folders
	 * @return list<ITrashItem>
	 */
	private function getTrashForFolders(IUser $user, array $folders): array {
		$folderIds = array_map(fn (FolderDefinitionWithPermissions $folder): int => $folder->id, $folders);
		$rows = $this->trashManager->listTrashForFolders($folderIds);
		$indexedRows = [];
		foreach ($rows as $row) {
			$key = $row['folder_id'] . '/' . $row['name'] . '/' . $row['deleted_time'];
			$indexedRows[$key] = $row;
		}

		$items = [];
		foreach ($folders as $folder) {
			// note that we explicitly don't pass the user here, was we need to get all trash items,
			// not only the trash items we have access to (so we can get their original paths)
			// we apply acl filtering later to get the correct permissions again
			$trashFolder = $this->setupTrashFolder($folder);
			$content = $trashFolder->getDirectoryListing();
			$userCanManageAcl = $this->folderManager->canManageACL($folder->id, $user);
			$this->aclManagerFactory->getACLManager($user)->preloadRulesForFolder($folder->storageId, $trashFolder->getId());

			$itemsForFolder = array_map(function (Node $item) use ($user, $folder, $indexedRows): \OCA\GroupFolders\Trash\GroupTrashItem {
				$pathParts = pathinfo($item->getName());
				$timestamp = (int)substr($pathParts['extension'], 1);
				$name = $pathParts['filename'];
				$key = $folder->id . '/' . $name . '/' . $timestamp;

				$originalLocation = isset($indexedRows[$key]) ? $indexedRows[$key]['original_location'] : '';
				$deletedBy = isset($indexedRows[$key]) ? $indexedRows[$key]['deleted_by'] : '';

				return new GroupTrashItem(
					$this,
					$originalLocation,
					$timestamp,
					'/' . $folder->id . '/' . $item->getName(),
					$item,
					$user,
					$folder->mountPoint,
					$this->userManager->get($deletedBy),
					$folder,
				);
			}, $content);
			$originalLocations = array_map(fn (GroupTrashItem $item): string => $item->getOriginalLocation(), $itemsForFolder);
			$itemsByOriginalLocation = array_combine($originalLocations, $itemsForFolder);

			// perform per-item ACL checks if the user doesn't have manage permissions
			if ($folder->acl && !$userCanManageAcl) {
				$itemsForFolder = array_filter($itemsForFolder, function (GroupTrashItem $item) use ($itemsByOriginalLocation): bool {
					// if we for any reason lost track of the original location, hide the item for non-managers as a fail-safe
					if ($item->getInternalOriginalLocation() === '') {
						return false;
					}

					if (!$this->userHasAccessToItem($item)) {
						return false;
					}

					// if a parent of the original location has also been deleted, we also need to check it based on the now-deleted parent path
					foreach ($this->getDeletedParentOriginalPaths($item->getOriginalLocation(), $itemsByOriginalLocation) as $parentItem) {
						$pathInsideParentItem = dirname(substr($item->getInternalOriginalLocation(), strlen($parentItem->getInternalOriginalLocation())));
						if (!$this->userHasAccessToItem($parentItem, Constants::PERMISSION_READ, $pathInsideParentItem)) {
							return false;
						}
					}

					return true;
				});
			}
			$items = array_merge($items, $itemsForFolder);
		}

		return $items;
	}

	/**
	 * @param array<string, GroupTrashItem> $trashItemsByOriginalPath
	 * @return list<GroupTrashItem>
	 */
	private function getDeletedParentOriginalPaths(string $path, array $trashItemsByOriginalPath): array {
		$parentItems = [];
		while ($path !== '') {
			$path = dirname($path);

			if ($path === '.' || $path === '/') {
				break;
			} elseif (isset($trashItemsByOriginalPath[$path])) {
				$parentItems[] = $trashItemsByOriginalPath[$path];
			}
		}

		return $parentItems;
	}

	public function getTrashNodeById(IUser $user, int $fileId): ?Node {
		try {
			$folders = $this->folderManager->getFoldersForUser($user);
			foreach ($folders as $folder) {
				$trashFolder = $this->setupTrashFolder($folder, $user);
				if ($path = $trashFolder->getStorage()->getCache()->getPathById($fileId)) {
					return $trashFolder->get($path);
				}
			}
			return null;
		} catch (NotFoundException) {
			return null;
		}
	}

	public function cleanTrashFolder(FolderDefinitionWithPermissions $folder): void {
		$trashFolder = $this->setupTrashFolder($folder);

		foreach ($trashFolder->getDirectoryListing() as $node) {
			$node->delete();
		}

		$this->trashManager->emptyTrashbin($folder->id);
	}

	public function expire(Expiration $expiration): array {
		$size = 0;
		$count = 0;
		$folders = $this->folderManager->getAllFoldersWithSize();
		$folders = array_map(fn (FolderWithMappingsAndCache $folder): FolderDefinitionWithPermissions => FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL), $folders);
		foreach ($folders as $folder) {
			$folderId = $folder->id;
			$trashItems = $this->trashManager->listTrashForFolders([$folderId]);

			// calculate size of trash items
			$sizeInTrash = 0;
			$trashFolder = $this->setupTrashFolder($folder);
			$nodes = []; // cache
			foreach ($trashItems as $groupTrashItem) {
				$nodeName = $groupTrashItem['name'] . '.d' . $groupTrashItem['deleted_time'];
				try {
					$nodes[$nodeName] = $node = $trashFolder->get($nodeName);
				} catch (NotFoundException) {
					$this->trashManager->removeItem($folderId, $groupTrashItem['name'], $groupTrashItem['deleted_time']);
					continue;
				}

				$sizeInTrash += $node->getSize();
			}

			$size = $folder->rootCacheEntry->getSize();

			foreach ($trashItems as $groupTrashItem) {
				$nodeName = $groupTrashItem['name'] . '.d' . $groupTrashItem['deleted_time'];
				if (!isset($nodes[$nodeName])) {
					continue;
				}

				$node = $nodes[$nodeName];

				if ($expiration->isExpired($groupTrashItem['deleted_time'], $folder->quota > 0 && $folder->quota < ($size + $sizeInTrash))) {
					$this->logger->debug('expiring ' . $node->getPath());
					if ($node->getStorage()->unlink($node->getInternalPath()) === false) {
						$this->logger->error('Failed to remove item from trashbin: ' . $node->getPath());
						continue;
					}

					// only count up after checking if removal is possible
					$count += 1;
					$size += $node->getSize();
					$size -= $node->getSize();
					$node->getStorage()->getCache()->remove($node->getInternalPath());
					$this->trashManager->removeItem($folderId, $groupTrashItem['name'], $groupTrashItem['deleted_time']);
					if (!is_null($groupTrashItem['file_id']) && !is_null($this->versionsBackend)) {
						$this->versionsBackend->deleteAllVersionsForFile($folder, $groupTrashItem['file_id']);
					}
				} else {
					$this->logger->debug($node->getPath() . " isn't set to be expired yet, stopping expiry");
					break;
				}
			}
		}

		return [$count, $size];
	}
}
