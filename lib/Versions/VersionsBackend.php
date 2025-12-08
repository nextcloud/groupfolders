<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Versions;

use OCA\DAV\Connector\Sabre\Exception\Forbidden;
use OCA\Files_Versions\Versions\IDeletableVersionBackend;
use OCA\Files_Versions\Versions\IMetadataVersion;
use OCA\Files_Versions\Versions\IMetadataVersionBackend;
use OCA\Files_Versions\Versions\INeedSyncVersionBackend;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\IVersionsImporterBackend;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderDefinitionWithMappings;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class VersionsBackend implements IVersionBackend, IMetadataVersionBackend, IDeletableVersionBackend, INeedSyncVersionBackend, IVersionsImporterBackend {
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly MountProvider $mountProvider,
		private readonly LoggerInterface $logger,
		private readonly GroupVersionsMapper $groupVersionsMapper,
		private readonly IMimeTypeLoader $mimeTypeLoader,
		private readonly IUserSession $userSession,
		private readonly IMountManager $mountManager,
		private readonly IStorageFactory $storageFactory,
	) {
	}

	public function useBackendForStorage(IStorage $storage): bool {
		return true;
	}

	private function getFolderForFile(FileInfo $file): FolderDefinition {
		$storage = $file->getStorage();
		$mountPoint = $file->getMountPoint();

		// getting it from the mountpoint is more efficient
		if ($mountPoint instanceof GroupMountPoint) {
			return $mountPoint->getFolder();
		} elseif ($storage->instanceOfStorage(GroupFolderStorage::class)) {
			/** @var GroupFolderStorage $storage */
			return $storage->getFolder();
		}
		throw new \LogicException('Team folder version backend called for non Team folder file');
	}

	public function getVersionFolderForFile(FileInfo $file): Folder {
		$folder = $this->getFolderForFile($file);
		$groupFoldersVersionsFolder = $this->getVersionsFolder($folder);

		try {
			/** @var Folder $versionsFolder */
			$versionsFolder = $groupFoldersVersionsFolder->get((string)$file->getId());

			return $versionsFolder;
		} catch (NotFoundException) {
			// The folder for the file's versions might not exists if no versions has been create yet.
			return $groupFoldersVersionsFolder->newFolder((string)$file->getId());
		}
	}

	/**
	 * @return GroupVersion[]
	 */
	public function getVersionsForFile(IUser $user, FileInfo $file): array {
		$versionsFolder = $this->getVersionFolderForFile($file);

		try {
			$versions = $this->getVersionsForFileFromDB($file, $user);

			// Early exit if we find any version in the database.
			// Else we continue to populate the DB from what's on disk.
			if (count($versions) > 0) {
				return $versions;
			}

			// Insert the entry in the DB for the current version.
			$versionEntity = new GroupVersionEntity();
			$versionEntity->setFileId($file->getId());
			$versionEntity->setTimestamp($file->getMTime());
			$versionEntity->setSize($file->getSize());
			$versionEntity->setMimetype($this->mimeTypeLoader->getId($file->getMimetype()));
			$versionEntity->setDecodedMetadata([]);
			$this->groupVersionsMapper->insert($versionEntity);

			// Insert entries in the DB for existing versions.
			$versionsOnFS = $versionsFolder->getDirectoryListing();
			foreach ($versionsOnFS as $version) {
				if ($version instanceof Folder) {
					$this->logger->error('Found an unexpected subfolder inside the Team folder version folder.');
				}

				$versionEntity = new GroupVersionEntity();
				$versionEntity->setFileId($file->getId());
				// HACK: before this commit, versions were created with the current timestamp instead of the version's mtime.
				// This means that the name of some versions is the exact mtime of the next version. This behavior is now fixed.
				// To prevent occasional conflicts between the last version and the current one, we decrement the last version mtime.
				$mtime = (int)$version->getName();
				if ($mtime === $file->getMTime()) {
					$versionEntity->setTimestamp($mtime - 1);
					$version->move($version->getParent()->getPath() . '/' . ($mtime - 1));
				} else {
					$versionEntity->setTimestamp($mtime);
				}

				$versionEntity->setSize($version->getSize());
				// Use the main file mimetype for this initialization as the original mimetype is unknown.
				$versionEntity->setMimetype($this->mimeTypeLoader->getId($file->getMimetype()));
				$versionEntity->setDecodedMetadata([]);
				$this->groupVersionsMapper->insert($versionEntity);
			}

			return $this->getVersionsForFileFromDB($file, $user);
		} catch (NotFoundException) {
			return [];
		}
	}

	/**
	 * @return GroupVersion[]
	 */
	private function getVersionsForFileFromDB(FileInfo $fileInfo, IUser $user): array {
		$folder = $this->getFolderForFile($fileInfo);
		$mountPoint = $fileInfo->getMountPoint();
		if (!$mountPoint instanceof GroupMountPoint) {
			return [];
		}
		$versionsFolder = $this->getVersionFolderForFile($fileInfo);

		$versionEntities = $this->groupVersionsMapper->findAllVersionsForFileId($fileInfo->getId());
		$mappedVersions = array_map(
			function (GroupVersionEntity $versionEntity) use ($versionsFolder, $mountPoint, $fileInfo, $user, $folder): ?GroupVersion {
				$currentVersion = false;
				if ($fileInfo->getMtime() === $versionEntity->getTimestamp()) {
					$currentVersion = true;
					if ($fileInfo instanceof File) {
						$versionFile = $fileInfo;
					} else {
						$versionFile = $this->rootFolder->get($fileInfo->getPath());
					}
				} else {
					try {
						$versionFile = $versionsFolder->get((string)$versionEntity->getTimestamp());
					} catch (NotFoundException) {
						// The version does not exists on disk anymore, so we can delete its entity in the DB.
						// The reality is that the disk version might have been lost during a move operation between storages,
						// and its not possible to recover it, so removing the entity makes sense.
						$this->groupVersionsMapper->delete($versionEntity);

						return null;
					}
				}

				return new GroupVersion(
					$versionEntity->getTimestamp(),
					$versionEntity->getTimestamp(),
					$fileInfo->getName(),
					$versionEntity->getSize(),
					$this->mimeTypeLoader->getMimetypeById($versionEntity->getMimetype()),
					$mountPoint->getInternalPath($fileInfo->getPath()),
					$fileInfo,
					$this,
					$user,
					$versionEntity->getDecodedMetadata(),
					$versionFile,
					$folder,
					$currentVersion,
				);
			},
			$versionEntities,
		);
		// Filter out null values.
		return array_filter($mappedVersions);
	}

	public function createVersion(IUser $user, FileInfo $file): void {
		$versionsFolder = $this->getVersionFolderForFile($file);

		$versionMount = $versionsFolder->getMountPoint();
		$sourceMount = $file->getMountPoint();
		$sourceCache = $sourceMount->getStorage()->getCache();
		$revision = $file->getMtime();

		$versionInternalPath = $versionsFolder->getInternalPath() . '/' . $revision;
		$sourceInternalPath = $file->getInternalPath();

		$versionMount->getStorage()->copyFromStorage($sourceMount->getStorage(), $sourceInternalPath, $versionInternalPath);
		$versionMount->getStorage()->getCache()->copyFromCache($sourceCache, $sourceCache->get($sourceInternalPath), $versionInternalPath);
	}

	public function rollback(IVersion $version): void {
		if (!($version instanceof GroupVersion)) {
			throw new \LogicException('Trying to restore a version from a file not in a Team folder');
		}

		if (!$this->currentUserHasPermissions($version->getSourceFile(), \OCP\Constants::PERMISSION_UPDATE)) {
			throw new Forbidden('You cannot restore this version because you do not have update permissions on the source file.');
		}

		$this->createVersion($version->getUser(), $version->getSourceFile());

		/** @var GroupMountPoint $targetMount */
		$targetMount = $version->getSourceFile()->getMountPoint();
		$targetCache = $targetMount->getStorage()->getCache();
		$versionMount = $version->getVersionFile()->getMountPoint();
		$versionCache = $versionMount->getStorage()->getCache();

		$targetInternalPath = $version->getSourceFile()->getInternalPath();
		$versionInternalPath = $version->getVersionFile()->getInternalPath();

		$targetMount->getStorage()->copyFromStorage($versionMount->getStorage(), $versionInternalPath, $targetInternalPath);
		$targetCache->copyFromCache($versionCache, $versionCache->get($versionInternalPath), $targetInternalPath);
	}

	public function read(IVersion $version) {
		if ($version instanceof GroupVersion) {
			return $version->getVersionFile()->fopen('r');
		} else {
			return false;
		}
	}

	public function getVersionFile(IUser $user, FileInfo $sourceFile, $revision): File {
		$versionsFolder = $this->getVersionFolderForFile($sourceFile);
		$file = $versionsFolder->get((string)$revision);
		assert($file instanceof File);

		return $file;
	}

	/**
	 * @param FolderWithMappingsAndCache $folder
	 * @return array<int, ?FileInfo>
	 */
	public function getAllVersionedFiles(FolderDefinitionWithMappings $folder): array {
		$versionsFolder = $this->getVersionsFolder($folder);
		$folderWithPermissions = FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL);
		$mount = $this->mountProvider->getMount($folderWithPermissions, '/groupfolders/' . $folder->mountPoint);
		if ($mount === null) {
			$this->logger->error('Tried to get all the versioned files from a non existing mountpoint');
			return [];
		}
		$this->mountManager->addMount($mount);

		try {
			$contents = $versionsFolder->getDirectoryListing();
		} catch (NotFoundException) {
			return [];
		}

		$fileIds = array_map(fn (Node $node): int => (int)$node->getName(), $contents);
		$files = array_map(function (int $fileId) use ($mount): ?FileInfo {
			$cacheEntry = $mount->getStorage()->getCache()->get($fileId);
			if ($cacheEntry) {
				return new \OC\Files\FileInfo($mount->getMountPoint() . '/' . $cacheEntry->getPath(), $mount->getStorage(), $cacheEntry->getPath(), $cacheEntry, $mount);
			} else {
				return null;
			}
		}, $fileIds);

		return array_combine($fileIds, $files);
	}

	public function deleteAllVersionsForFile(FolderDefinition $folder, int $fileId): void {
		$versionsFolder = $this->getVersionsFolder($folder);
		try {
			$versionsFolder->get((string)$fileId)->delete();
			$this->groupVersionsMapper->deleteAllVersionsForFileId($fileId);
		} catch (NotFoundException) {
		}
	}

	public function getVersionsFolder(FolderDefinition $folder): Folder {
		$mountPoint = '/dummy/files_versions/groupfolders/' . $folder->id;
		$mount = $this->mountManager->find($mountPoint);
		// check that $mount is the version mount, and not a mount for a parent folder
		if ($mount->getMountPoint() !== $mountPoint) {
			$versionMount = $this->mountProvider->getVersionsMount(
				$folder,
				$mountPoint,
				$this->storageFactory,
			);
			$this->mountManager->addMount($versionMount);
		}
		return $this->rootFolder->get($mountPoint);
	}

	public function setMetadataValue(Node $node, int $revision, string $key, string $value): void {
		if (!$this->currentUserHasPermissions($node, \OCP\Constants::PERMISSION_UPDATE)) {
			throw new Forbidden('You cannot update the version\'s metadata because you do not have update permissions on the source file.');
		}

		$versionEntity = $this->groupVersionsMapper->findVersionForFileId($node->getId(), $revision);

		$versionEntity->setMetadataValue($key, $value);
		$this->groupVersionsMapper->update($versionEntity);
	}

	public function deleteVersion(IVersion $version): void {
		if (!$this->currentUserHasPermissions($version->getSourceFile(), \OCP\Constants::PERMISSION_DELETE)) {
			throw new Forbidden('You cannot delete this version because you do not have delete permissions on the source file.');
		}

		$sourceFile = $version->getSourceFile();
		$mount = $sourceFile->getMountPoint();

		if (!($mount instanceof GroupMountPoint)) {
			return;
		}

		$folder = $this->getFolderForFile($sourceFile);
		$versionsFolder = $this->getVersionsFolder($folder)->get((string)$sourceFile->getId());
		/** @var Folder $versionsFolder */
		$versionsFolder->get((string)$version->getRevisionId())->delete();

		$versionEntity = $this->groupVersionsMapper->findVersionForFileId(
			$version->getSourceFile()->getId(),
			$version->getTimestamp(),
		);
		$this->groupVersionsMapper->delete($versionEntity);
	}

	public function createVersionEntity(File $file): void {
		$fileId = $file->getId();
		$timestamp = $file->getMTime();
		try {
			$this->groupVersionsMapper->findVersionForFileId($fileId, $timestamp);
		} catch (DoesNotExistException) {
			$versionEntity = new GroupVersionEntity();
			$versionEntity->setFileId($fileId);
			$versionEntity->setTimestamp($timestamp);
			$versionEntity->setSize($file->getSize());
			$versionEntity->setMimetype($this->mimeTypeLoader->getId($file->getMimetype()));
			$versionEntity->setDecodedMetadata([]);
			$this->groupVersionsMapper->insert($versionEntity);
		}
	}

	public function updateVersionEntity(File $sourceFile, int $revision, array $properties): void {
		$versionEntity = $this->groupVersionsMapper->findVersionForFileId($sourceFile->getId(), $revision);

		if (isset($properties['timestamp'])) {
			$versionEntity->setTimestamp($properties['timestamp']);
		}

		if (isset($properties['size'])) {
			$versionEntity->setSize($properties['size']);
		}

		if (isset($properties['mimetype'])) {
			$versionEntity->setMimetype($properties['mimetype']);
		}

		$this->groupVersionsMapper->update($versionEntity);
	}

	public function deleteVersionsEntity(File $file): void {
		$this->groupVersionsMapper->deleteAllVersionsForFileId($file->getId());
	}

	private function currentUserHasPermissions(FileInfo $sourceFile, int $permissions): bool {
		$currentUserId = $this->userSession->getUser()?->getUID();

		if ($currentUserId === null) {
			throw new NotFoundException('No user logged in');
		}

		return ($sourceFile->getPermissions() & $permissions) === $permissions;
	}

	/**
	 * @inheritdoc
	 * @psalm-suppress MethodSignatureMismatch - The signature of the method is correct, but psalm somehow can't understand it
	 */
	public function importVersionsForFile(IUser $user, Node $source, Node $target, array $versions): void {
		$mount = $target->getMountPoint();
		if (!($mount instanceof GroupMountPoint)) {
			return;
		}

		$versionsFolder = $this->getVersionFolderForFile($target);

		foreach ($versions as $version) {
			// 1. Move the file to the new location
			if ($version->getTimestamp() !== $source->getMTime()) {
				$backend = $version->getBackend();
				$versionFile = $backend->getVersionFile($user, $source, $version->getRevisionId());
				$versionsFolder->newFile($version->getRevisionId(), $versionFile->fopen('r'));
			}

			// 2. Create the entity in the database
			$versionEntity = new GroupVersionEntity();
			$versionEntity->setFileId($target->getId());
			$versionEntity->setTimestamp($version->getTimestamp());
			$versionEntity->setSize($version->getSize());
			$versionEntity->setMimetype($this->mimeTypeLoader->getId($version->getMimetype()));
			if ($version instanceof IMetadataVersion) {
				$versionEntity->setDecodedMetadata($version->getMetadata());
			}

			$this->groupVersionsMapper->insert($versionEntity);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function clearVersionsForFile(IUser $user, Node $source, Node $target): void {
		$folder = $this->getFolderForFile($source);
		$this->deleteAllVersionsForFile($folder, $target->getId());
	}

	public function getRevision(\OC\Files\Node\Node $node): int {
		return $node->getMTime();
	}
}
