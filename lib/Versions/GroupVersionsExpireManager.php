<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Versions;

use OC\Files\View;
use OC\Hooks\BasicEmitter;
use OC\User\User;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\IUser;

class GroupVersionsExpireManager extends BasicEmitter {
	public function __construct(
		private FolderManager $folderManager,
		private ExpireManager $expireManager,
		private VersionsBackend $versionsBackend,
		private ITimeFactory $timeFactory,
		private IEventDispatcher $dispatcher,
	) {
	}

	public function expireAll(): void {
		$folders = $this->folderManager->getAllFolders();
		foreach ($folders as $folder) {
			$this->emit(self::class, 'enterFolder', [$folder]);
			$this->expireFolder($folder);
		}
	}

	public function expireFolders(array $folders): void {
		foreach ($folders as $folder) {
			$this->emit(self::class, 'enterFolder', [$folder]);
			$this->expireFolder($folder);
		}
	}

	/**
	 * @param array{acl: bool, groups: array<array-key, array<array-key, int|string>>, id: int, mount_point: mixed, quota: int, size: 0} $folder
	 */
	public function expireFolder(array $folder): void {
		$view = new View('/__groupfolders/versions/' . $folder['id']);
		$files = $this->versionsBackend->getAllVersionedFiles($folder);
		/** @var IUser */
		$dummyUser = new User('', null, $this->dispatcher);
		foreach ($files as $fileId => $file) {
			if ($file instanceof FileInfo) {
				// Some versions could have been lost during move operations across storage.
				// When this is the case, the fileinfo's path will not contains the name.
				// When this is the case, we unlink the version's folder for the fileid, and continue to the next file.
				if (!str_ends_with($file->getPath(), $file->getName())) {
					$view->unlink('/' . $fileId);
					continue;
				}

				$versions = $this->versionsBackend->getVersionsForFile($dummyUser, $file);
				$expireVersions = $this->expireManager->getExpiredVersion($versions, $this->timeFactory->getTime(), false);
				foreach ($expireVersions as $version) {
					/** @var GroupVersion $version */
					$this->emit(self::class, 'deleteVersion', [$version]);
					$view->unlink('/' . $fileId . '/' . $version->getVersionFile()->getName());
				}
			} else {
				// source file no longer exists
				$this->emit(self::class, 'deleteFile', [$fileId]);
				$this->versionsBackend->deleteAllVersionsForFile($folder['id'], $fileId);
			}
		}
	}
}
