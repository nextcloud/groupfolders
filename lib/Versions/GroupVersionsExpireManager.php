<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Versions;

use OC\Files\FileInfo;
use OC\Files\View;
use OC\Hooks\BasicEmitter;
use OC\User\User;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\AppFramework\Utility\ITimeFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GroupVersionsExpireManager extends BasicEmitter {
	private $folderManager;
	private $expireManager;
	private $versionsBackend;
	private $timeFactory;
	private $dispatcher;

	public function __construct(
		FolderManager $folderManager,
		ExpireManager $expireManager,
		VersionsBackend $versionsBackend,
		ITimeFactory $timeFactory,
		EventDispatcherInterface $dispatcher
	) {
		$this->folderManager = $folderManager;
		$this->expireManager = $expireManager;
		$this->versionsBackend = $versionsBackend;
		$this->timeFactory = $timeFactory;
		$this->dispatcher = $dispatcher;
	}

	public function expireAll(): void {
		$folders = $this->folderManager->getAllFolders();
		foreach ($folders as $folder) {
			$this->emit(self::class, 'enterFolder', [$folder]);
			$this->expireFolder($folder);
		}
	}

	/**
	 * @param array{id: int, mount_point: string, groups: array<empty, empty>|array<array-key, int>, quota: int, size: int, acl: bool} $folder
	 */
	public function expireFolder(array $folder): void {
		$view = new View('/__groupfolders/versions/' . $folder['id']);
		$files = $this->versionsBackend->getAllVersionedFiles($folder);
		$dummyUser = new User('', null, $this->dispatcher);
		foreach ($files as $fileId => $file) {
			if ($file instanceof FileInfo) {
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
