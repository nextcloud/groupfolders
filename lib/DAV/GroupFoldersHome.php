<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\DAV;

use OC\Files\Filesystem;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\DB\Exception;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUser;
use RuntimeException;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class GroupFoldersHome implements ICollection {
	public function __construct(
		private array $principalInfo,
		private FolderManager $folderManager,
		private IRootFolder $rootFolder,
		private IUser $user,
	) {
	}

	/**
	 * @throws Forbidden
	 */
	public function delete(): never {
		throw new Forbidden();
	}

	public function getName(): string {
		[, $name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	/**
	 * @throws Forbidden
	 */
	public function setName($name): never {
		throw new Forbidden('Permission denied to rename this folder');
	}

	/**
	 * @throws Forbidden
	 */
	public function createFile($name, $data = null): never {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	/**
	 * @throws Forbidden
	 */
	public function createDirectory($name): never {
		throw new Forbidden('Permission denied to create folders in this folder');
	}

	/**
	 * @return array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}|null
	 * @throws Exception
	 * @throws RequestBuilderException
	 */
	private function getFolder(string $name): ?array {
		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return null;
		}

		$folders = $this->folderManager->getFoldersForUser($this->user, $storageId);
		foreach ($folders as $folder) {
			if (basename($folder['mount_point']) === $name) {
				return $folder;
			}
		}

		return null;
	}

	/**
	 * @param array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry} $folder
	 * @throws NotFoundException
	 */
	private function getDirectoryForFolder(array $folder): GroupFolderNode {
		$userHome = '/' . $this->user->getUID() . '/files';
		$node = $this->rootFolder->get($userHome . '/' . $folder['mount_point']);

		$view = Filesystem::getView();
		if ($view === null) {
			throw new RuntimeException('Unable to create view.');
		}

		return new GroupFolderNode($view, $node, $folder['folder_id']);
	}

	/**
	 * @throws NotFound
	 * @throws NotFoundException
	 * @throws RequestBuilderException
	 * @throws Exception
	 */
	public function getChild($name): GroupFolderNode {
		$folder = $this->getFolder($name);
		if ($folder) {
			return $this->getDirectoryForFolder($folder);
		}

		throw new NotFound();
	}

	/**
	 * @return GroupFolderNode[]
	 * @throws RequestBuilderException
	 * @throws Exception
	 */
	public function getChildren(): array {
		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return [];
		}

		$folders = $this->folderManager->getFoldersForUser($this->user, $storageId);
		return array_map($this->getDirectoryForFolder(...), $folders);
	}

	/**
	 * @throws Exception
	 * @throws RequestBuilderException
	 */
	public function childExists($name): bool {
		return $this->getFolder($name) !== null;
	}

	public function getLastModified(): int {
		return 0;
	}
}
