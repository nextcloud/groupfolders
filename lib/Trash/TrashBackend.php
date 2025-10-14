<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Trash;

use OC\Encryption\Exceptions\DecryptionFailedException;
use OC\Files\Storage\Wrapper\Encryption;
use OC\Files\Storage\Wrapper\Jail;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Storage;
use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\ITrashItem;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\Constants;
use OCP\Files\FileInfo;
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

/**
 * @psalm-import-type InternalFolder from FolderManager
 */
class TrashBackend implements ITrashBackend {
	private ?VersionsBackend $versionsBackend = null;

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
		private IMountManager $mountManager,
		private IStorageFactory $storageFactory,
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
		$this->aclManagerFactory->getACLManager($user)->preloadRulesForFolder($folder->getPath());

		return array_values(array_filter(array_map(function (Node $node) use ($folder, $user): ?GroupTrashItem {
			if (!$this->userHasAccessToPath($user, $this->getUnJailedPath($node))) {
				return null;
			}

			return new GroupTrashItem(
				$this,
				$folder->getInternalOriginalLocation() . '/' . $node->getName(),
				$folder->getDeletedTime(),
				$folder->getTrashPath() . '/' . $node->getName(),
				$node,
				$user,
				$folder->getGroupFolderMountPoint(),
				$folder->getDeletedBy(),
			);
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
		[, $folderId] = explode('/', $item->getTrashPath());
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}

		if (!$this->userHasAccessToPath($item->getUser(), $this->getUnJailedPath($node), Constants::PERMISSION_UPDATE)) {
			throw new NotPermittedException();
		}

		$folderPermissions = $this->folderManager->getFolderPermissionsForUser($item->getUser(), (int)$folderId);
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
		} catch (DecryptionFailedException $e) {
			// Before https://github.com/nextcloud/groupfolders/pull/3425 the key would be in the wrong place, leading to the decryption failure.
			// for those we fall back to the old restore behavior
			[$unwrappedTargetStorage, $unwrappedTargetLocation] = $this->unwrapJails($targetStorage, $targetLocation);
			[$unwrappedTrashStorage, $unwrappedTrashLocation] = $this->unwrapJails($trashStorage, $trashLocation);
			$unwrappedTargetStorage->moveFromStorage($unwrappedTrashStorage, $unwrappedTrashLocation, $unwrappedTargetLocation);
			$unwrappedTargetStorage->getUpdater()->renameFromStorage($unwrappedTrashStorage, $unwrappedTrashLocation, $unwrappedTargetLocation);
		}
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
			/** @var GroupFolderStorage $storage */
			$name = basename($internalPath);
			$fileEntry = $storage->getCache()->get($internalPath);
			$folderId = $storage->getFolderId();
			$user = $this->userSession->getUser();

			$owner = $storage->getUser();

			$this->setupTrashFolder($folderId, $owner);

			$trashFolder = $this->rootFolder->get('/' . $owner->getUID() . '/files_trashbin/groupfolders/' . $folderId);
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

				$this->trashManager->addTrashItem($folderId, $name, $time, $originalLocation, $fileEntry->getId(), $user?->getUID() ?? '');

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
		$folders = $this->folderManager->getFoldersForUser($user);
		$folderIds = array_map(fn (array $folder): int => $folder['folder_id'], $folders);

		return in_array($folderId, $folderIds);
	}

	private function userHasAccessToPath(
		IUser $user,
		string $path,
		int $permission = Constants::PERMISSION_READ,
	): bool {
		$activePermissions = $this->aclManagerFactory->getACLManager($user)
			->getACLPermissionsForPath($path);

		return (bool)($activePermissions & $permission);
	}

	private function getNodeForTrashItem(IUser $user, ITrashItem $trashItem): ?Node {
		[, $folderId, $path] = explode('/', $trashItem->getTrashPath(), 3);
		$folderId = (int)$folderId;
		$folders = $this->folderManager->getFoldersForUser($user);
		foreach ($folders as $groupFolder) {
			if ($groupFolder['folder_id'] === $folderId) {
				$trashRoot = $this->rootFolder->get('/' . $user->getUID() . '/files_trashbin/groupfolders/' . $folderId);
				try {
					$node = $trashRoot->get($path);
					if (!$this->userHasAccessToPath($user, $trashItem->getPath())) {
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

	private function getTrashRoot(): Folder {
		try {
			/** @var Folder $folder */
			$folder = $this->appFolder->get('trash');

			return $folder;
		} catch (NotFoundException) {
			return $this->appFolder->newFolder('trash');
		}
	}

	private function setupTrashFolder(int $folderId, ?IUser $user = null): Folder {
		if ($user) {
			$mountPoint = '/' . $user->getUID() . '/files_trashbin/groupfolders/' . $folderId;
			$mount = $this->mountManager->find($mountPoint);
			if ($mount->getMountPoint() !== $mountPoint) {
				$trashMount = $this->mountProvider->getTrashMount(
					$folderId,
					$mountPoint,
					FileInfo::SPACE_UNLIMITED,
					$this->storageFactory,
					$user,
				);
				$this->mountManager->addMount($trashMount);
			}
		}

		try {
			/** @var Folder $folder */
			$folder = $this->appFolder->get('trash/' . $folderId);

			return $folder;
		} catch (NotFoundException) {
			/** @var Folder $trashRoot */
			$trashRoot = $this->appFolder->nodeExists('trash') ? $this->appFolder->get('trash') : $this->appFolder->newFolder('trash');

			return $trashRoot->newFolder((string)$folderId);
		}
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
	 * @param list<InternalFolder> $folders
	 * @return list<ITrashItem>
	 */
	private function getTrashForFolders(IUser $user, array $folders): array {
		$folderIds = array_map(fn (array $folder): int => $folder['folder_id'], $folders);
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

			// ensure the trash folder exists
			$this->setupTrashFolder($folderId, $user);

			$trashFolder = $this->rootFolder->get('/' . $user->getUID() . '/files_trashbin/groupfolders/' . $folderId);
			$content = $trashFolder->getDirectoryListing();
			$userCanManageAcl = $this->folderManager->canManageACL($folderId, $user);
			$this->aclManagerFactory->getACLManager($user)->preloadRulesForFolder($this->getUnJailedPath($trashFolder));
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

					if (!$this->userHasAccessToPath($user, $this->getUnJailedPath($item))) {
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
		} catch (NotFoundException) {
			return null;
		}
	}

	public function cleanTrashFolder(int $folderid): void {
		$trashFolder = $this->setupTrashFolder($folderid);

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
			$trashFolder = $this->setupTrashFolder($folderId);
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

			foreach ($trashItems as $groupTrashItem) {
				$nodeName = $groupTrashItem['name'] . '.d' . $groupTrashItem['deleted_time'];
				if (!isset($nodes[$nodeName])) {
					continue;
				}

				$node = $nodes[$nodeName];

				if ($expiration->isExpired($groupTrashItem['deleted_time'], $folder['quota'] > 0 && $folder['quota'] < ($folder['size'] + $sizeInTrash))) {
					$this->logger->debug('expiring ' . $node->getPath());
					if ($node->getStorage()->unlink($node->getInternalPath()) === false) {
						$this->logger->error('Failed to remove item from trashbin: ' . $node->getPath());
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
	 */
	private function cleanupDeletedFoldersTrash(array $existingFolders): void {
		$trashRoot = $this->getTrashRoot();
		foreach ($trashRoot->getDirectoryListing() as $trashFolder) {
			$folderId = $trashFolder->getName();
			if (is_numeric($folderId)) {
				$folderId = (int)$folderId;
				if (!isset($existingFolders[$folderId])) {
					$this->cleanTrashFolder($folderId);
					$this->setupTrashFolder($folderId)->delete();
				}
			}
		}
	}
}
