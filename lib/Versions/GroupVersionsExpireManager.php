<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Versions;

use OC\User\User;
use OCA\GroupFolders\Event\GroupVersionsExpireDeleteFileEvent;
use OCA\GroupFolders\Event\GroupVersionsExpireDeleteVersionEvent;
use OCA\GroupFolders\Event\GroupVersionsExpireEnterFolderEvent;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class GroupVersionsExpireManager {
	public function __construct(
		private readonly FolderManager $folderManager,
		private readonly ExpireManager $expireManager,
		private readonly VersionsBackend $versionsBackend,
		private readonly ITimeFactory $timeFactory,
		private readonly IEventDispatcher $dispatcher,
		private readonly LoggerInterface $logger,
	) {
	}

	public function expireAll(): void {
		$folders = $this->folderManager->getAllFoldersWithSize();
		$this->expireFolders($folders);
	}

	/**
	 * @param FolderWithMappingsAndCache[] $folders
	 */
	public function expireFolders(array $folders): void {
		foreach ($folders as $folder) {
			$this->dispatcher->dispatchTyped(new GroupVersionsExpireEnterFolderEvent($folder));
			$this->expireFolder($folder);
		}
	}

	public function expireFolder(FolderWithMappingsAndCache $folder): void {
		$baseFolder = $this->versionsBackend->getVersionsFolder($folder);
		$files = $this->versionsBackend->getAllVersionedFiles($folder);
		/** @var IUser */
		$dummyUser = new User('', null, $this->dispatcher);
		foreach ($files as $fileId => $file) {
			if ($file instanceof FileInfo) {
				// Some versions could have been lost during move operations across storage.
				// When this is the case, the fileinfo's path will not contains the name.
				// When this is the case, we unlink the version's folder for the fileid, and continue to the next file.
				if (!str_ends_with($file->getPath(), $file->getName())) {
					$baseFolder->get((string)$fileId)->delete();
					continue;
				}

				$versions = $this->versionsBackend->getVersionsForFile($dummyUser, $file);
				$expireVersions = $this->expireManager->getExpiredVersion($versions, $this->timeFactory->getTime(), false);
				foreach ($expireVersions as $version) {
					if ($version->isCurrentVersion()) {
						$this->logger->error(
							'Current version of a groupfolders file was listed for deletion. Skipping.',
							[
								'folderid' => $version->getFolderId(),
								'timestamp' => $version->getTimestamp(),
								'mtime' => $version->getSourceFile()->getMtime(),
								'sourcefilename' => $version->getSourceFileName(),
							]
						);
						continue;
					}
					/** @var GroupVersion $version */
					$this->dispatcher->dispatchTyped(new GroupVersionsExpireDeleteVersionEvent($version));
					$version->getVersionFile()->delete();
				}
			} else {
				// source file no longer exists
				$this->dispatcher->dispatchTyped(new GroupVersionsExpireDeleteFileEvent($fileId));
				$this->versionsBackend->deleteAllVersionsForFile($folder, $fileId);
			}
		}
	}
}
