<?php

declare(strict_types=1);
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

namespace OCA\GroupFolders\Versions;

use OC\User\NoUserException;
use OCA\Files_Versions\Versions\IDeletableVersionBackend;
use OCA\Files_Versions\Versions\INameableVersionBackend;
use OCA\Files_Versions\Versions\INeedSyncVersionBackend;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class VersionsBackend implements IVersionBackend, INameableVersionBackend, IDeletableVersionBackend, INeedSyncVersionBackend {
	public function __construct(
		private IRootFolder $rootFolder,
		private Folder $appFolder,
		private MountProvider $mountProvider,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
		private GroupVersionsMapper $groupVersionsMapper,
		private IMimeTypeLoader $mimeTypeLoader,
	) {
	}

	public function useBackendForStorage(IStorage $storage): bool {
		return true;
	}

	public function getVersionsForFile(IUser $user, FileInfo $file): array {
		$mount = $file->getMountPoint();
		if (!($mount instanceof GroupMountPoint)) {
			return [];
		}

		try {
			$folderId = $mount->getFolderId();
			/** @var Folder $versionsFolder */
			$versionsFolder = $this->getVersionsFolder($mount->getFolderId())->get((string)$file->getId());

			try {
				$userFolder = $this->rootFolder->getUserFolder($user->getUID());
			} catch (NoUserException $e) {
				return [];
			}
			$nodes = $userFolder->getById($file->getId());
			$node = array_pop($nodes);

			$versions = $this->getVersionsForFileFromDB($file, $user, $folderId);

			// Early exit if we find any version in the database.
			// Else we continue to populate the DB from what's on disk.
			if (count($versions) > 0) {
				return $versions;
			}

			// Insert the entry in the DB for the current version.
			$versionEntity = new GroupVersionEntity();
			$versionEntity->setFileId($node->getId());
			$versionEntity->setTimestamp($node->getMTime());
			$versionEntity->setSize($node->getSize());
			$versionEntity->setMimetype($this->mimeTypeLoader->getId($node->getMimetype()));
			$versionEntity->setDecodedMetadata([]);
			$this->groupVersionsMapper->insert($versionEntity);

			// Insert entries in the DB for existing versions.
			$versionsOnFS = $versionsFolder->getDirectoryListing();
			foreach ($versionsOnFS as $version) {
				if ($version instanceof Folder) {
					$this->logger->error('Found an unexpected subfolder inside the groupfolder version folder.');
				}

				$versionEntity = new GroupVersionEntity();
				$versionEntity->setFileId($node->getId());
				// HACK: before this commit, versions were created with the current timestamp instead of the version's mtime.
				// This means that the name of some versions is the exact mtime of the next version. This behavior is now fixed.
				// To prevent occasional conflicts between the last version and the current one, we decrement the last version mtime.
				$mtime = (int)$version->getName();
				if ($mtime === $node->getMTime()) {
					$versionEntity->setTimestamp($mtime - 1);
					$version->move($version->getParent()->getPath() . '/' . ($mtime - 1));
				} else {
					$versionEntity->setTimestamp($mtime);
				}
				$versionEntity->setSize($version->getSize());
				// Use the main file mimetype for this initialization as the original mimetype is unknown.
				$versionEntity->setMimetype($this->mimeTypeLoader->getId($node->getMimetype()));
				$versionEntity->setDecodedMetadata([]);
				$this->groupVersionsMapper->insert($versionEntity);
			}

			return $this->getVersionsForFileFromDB($node, $user, $folderId);
		} catch (NotFoundException $e) {
			return [];
		}
	}

	/**
	 * @return IVersion[]
	 */
	private function getVersionsForFileFromDB(FileInfo $file, IUser $user, int $folderId): array {
		try {
			$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		} catch (NoUserException $e) {
			return [];
		}

		/** @var Folder $versionsFolder */
		$versionsFolder = $this->getVersionsFolder($folderId)->get((string)$file->getId());

		return array_map(
			fn (GroupVersionEntity $versionEntity) => new GroupVersion(
				$versionEntity->getTimestamp(),
				$versionEntity->getTimestamp(),
				$file->getName(),
				$versionEntity->getSize(),
				$this->mimeTypeLoader->getMimetypeById($versionEntity->getMimetype()),
				$userFolder->getRelativePath($file->getPath()),
				$file,
				$this,
				$user,
				$versionEntity->getLabel(),
				$file->getMtime() === $versionEntity->getTimestamp() ? $file : $versionsFolder->get((string)$versionEntity->getTimestamp()),
				$folderId,
			),
			$this->groupVersionsMapper->findAllVersionsForFileId($file->getId())
		);
	}

	/**
	 * @return void
	 */
	public function createVersion(IUser $user, FileInfo $file) {
		$mount = $file->getMountPoint();
		if ($mount instanceof GroupMountPoint) {
			$folderId = $mount->getFolderId();
			$versionsFolder = $this->getVersionsFolder($folderId);

			try {
				/** @var Folder $versionFolder */
				$versionFolder = $versionsFolder->get((string)$file->getId());
			} catch (NotFoundException $e) {
				$versionFolder = $versionsFolder->newFolder((string)$file->getId());
			}

			$versionMount = $versionFolder->getMountPoint();
			$sourceMount = $file->getMountPoint();
			$sourceCache = $sourceMount->getStorage()->getCache();
			$revision = $file->getMtime();

			$versionInternalPath = $versionFolder->getInternalPath() . '/' . $revision;
			$sourceInternalPath = $file->getInternalPath();

			$versionMount->getStorage()->copyFromStorage($sourceMount->getStorage(), $sourceInternalPath, $versionInternalPath);
			$versionMount->getStorage()->getCache()->copyFromCache($sourceCache, $sourceCache->get($sourceInternalPath), $versionInternalPath);
		}
	}

	public function rollback(IVersion $version): void {
		if ($version instanceof GroupVersion) {
			$this->createVersion($version->getUser(), $version->getSourceFile());

			/** @var GroupMountPoint $targetMount */
			$targetMount = $version->getSourceFile()->getMountPoint();
			$targetCache = $targetMount->getStorage()->getCache();
			$versionMount = $version->getVersionFile()->getMountPoint();
			$versionCache = $versionMount->getStorage()->getCache();

			$targetInternalPath = $version->getSourceFile()->getInternalPath();
			$versionInternalPath = $version->getVersionFile()->getInternalPath();

			$targetMount->getStorage()->copyFromStorage($versionMount->getStorage(), $versionInternalPath, $targetInternalPath);
			$versionMount->getStorage()->getCache()->copyFromCache($targetCache, $versionCache->get($versionInternalPath), $targetMount->getSourcePath() . '/' . $targetInternalPath);
		}
	}

	public function read(IVersion $version) {
		if ($version instanceof GroupVersion) {
			return $version->getVersionFile()->fopen('r');
		} else {
			return false;
		}
	}

	public function getVersionFile(IUser $user, FileInfo $sourceFile, $revision): File {
		$mount = $sourceFile->getMountPoint();
		if (!($mount instanceof GroupMountPoint)) {
			throw new \LogicException('Trying to getVersionFile from a file not in a mounted group folder');
		}
		try {
			/** @var Folder $versionsFolder */
			$versionsFolder = $this->getVersionsFolder($mount->getFolderId())->get((string)$sourceFile->getId());
			$file = $versionsFolder->get((string)$revision);
			assert($file instanceof File);
			return $file;
		} catch (NotFoundException $e) {
			throw new \LogicException('Trying to getVersionFile from a file that doesn\'t exist');
		}
	}

	/**
	 * @param array{id: int, mount_point: string, groups: array<empty, empty>|mixed, quota: mixed, size: int, acl: bool} $folder
	 * @return (FileInfo|null)[] [$fileId => FileInfo|null]
	 */
	public function getAllVersionedFiles(array $folder) {
		$versionsFolder = $this->getVersionsFolder($folder['id']);
		$mount = $this->mountProvider->getMount($folder['id'], '/dummyuser/files/' . $folder['mount_point'], Constants::PERMISSION_ALL, $folder['quota']);
		if ($mount === null) {
			$this->logger->error('Tried to get all the versioned files from a non existing mountpoint');
			return [];
		}
		try {
			$contents = $versionsFolder->getDirectoryListing();
		} catch (NotFoundException $e) {
			return [];
		}

		$fileIds = array_map(function (Node $node) use ($mount): int {
			return (int)$node->getName();
		}, $contents);
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

	public function deleteAllVersionsForFile(int $folderId, int $fileId): void {
		$versionsFolder = $this->getVersionsFolder($folderId);
		try {
			$versionsFolder->get((string)$fileId)->delete();
			$this->groupVersionsMapper->deleteAllVersionsForFileId($fileId);
		} catch (NotFoundException $e) {
		}
	}

	private function getVersionsFolder(int $folderId): Folder {
		try {
			return $this->appFolder->get('versions/' . $folderId);
		} catch (NotFoundException $e) {
			/** @var Folder $trashRoot */
			$trashRoot = $this->appFolder->nodeExists('versions') ? $this->appFolder->get('versions') : $this->appFolder->newFolder('versions');
			return $trashRoot->newFolder((string)$folderId);
		}
	}

	public function setVersionLabel(IVersion $version, string $label): void {
		$versionEntity = $this->groupVersionsMapper->findVersionForFileId(
			$version->getSourceFile()->getId(),
			$version->getTimestamp(),
		);
		if (trim($label) === '') {
			$label = null;
		}
		$versionEntity->setLabel($label ?? '');
		$this->groupVersionsMapper->update($versionEntity);
	}

	public function deleteVersion(IVersion $version): void {
		$sourceFile = $version->getSourceFile();
		$mount = $sourceFile->getMountPoint();

		if (!($mount instanceof GroupMountPoint)) {
			return;
		}

		$versionsFolder = $this->getVersionsFolder($mount->getFolderId())->get((string)$sourceFile->getId());
		/** @var Folder $versionsFolder */
		$versionsFolder->get((string)$version->getRevisionId())->delete();

		$versionEntity = $this->groupVersionsMapper->findVersionForFileId(
			$version->getSourceFile()->getId(),
			$version->getTimestamp(),
		);
		$this->groupVersionsMapper->delete($versionEntity);
	}

	public function createVersionEntity(File $file): void {
		$versionEntity = new GroupVersionEntity();
		$versionEntity->setFileId($file->getId());
		$versionEntity->setTimestamp($file->getMTime());
		$versionEntity->setSize($file->getSize());
		$versionEntity->setMimetype($this->mimeTypeLoader->getId($file->getMimetype()));
		$versionEntity->setDecodedMetadata([]);
		$this->groupVersionsMapper->insert($versionEntity);
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
}
