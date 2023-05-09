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

use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\Constants;
use Psr\Log\LoggerInterface;

class VersionsBackend implements IVersionBackend {
	private Folder $appFolder;
	private MountProvider $mountProvider;
	private ITimeFactory $timeFactory;
	private LoggerInterface $logger;

	public function __construct(Folder $appFolder, MountProvider $mountProvider, ITimeFactory $timeFactory, LoggerInterface $logger) {
		$this->appFolder = $appFolder;
		$this->mountProvider = $mountProvider;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
	}

	public function useBackendForStorage(IStorage $storage): bool {
		return true;
	}

	public function getVersionsForFile(IUser $user, FileInfo $file): array {
		$mount = $file->getMountPoint();
		if ($mount instanceof GroupMountPoint) {
			try {
				$folderId = $mount->getFolderId();
				/** @var Folder $versionsFolder */
				$versionsFolder = $this->getVersionsFolder($mount->getFolderId())->get((string)$file->getId());
				$versions = array_map(function (Node $versionFile) use ($file, $user, $folderId): GroupVersion {
					if ($versionFile instanceof Folder) {
						$this->logger->error('Found an unexpected subfolder inside the groupfolder version folder.');
					}
					return new GroupVersion(
						(int)$versionFile->getName(),
						(int)$versionFile->getName(),
						$file->getName(),
						$versionFile->getSize(),
						$versionFile->getMimetype(),
						$versionFile->getPath(),
						$file,
						$this,
						$user,
						$versionFile,
						$folderId
					);
				}, $versionsFolder->getDirectoryListing());
				usort($versions, function (GroupVersion $v1, GroupVersion $v2): int {
					return $v2->getTimestamp() <=> $v1->getTimestamp();
				});
				return $versions;
			} catch (NotFoundException $e) {
				return [];
			}
		} else {
			return [];
		}
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
			$revision = $file->getMTime();

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
			$targetMount->getStorage()->touch($targetInternalPath, intval($version->getRevisionId()));
			$targetMount->getStorage()->getScanner()->scan($targetInternalPath);
			$versionMount->getStorage()->unlink($versionInternalPath);
			$versionMount->getStorage()->getCache()->remove($versionInternalPath);
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
}
