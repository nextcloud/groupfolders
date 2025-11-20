<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\DAV;

use OC\Files\Filesystem;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Files\IRootFolder;
use OCP\IUser;
use RuntimeException;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class GroupFoldersHome implements ICollection {
	public function __construct(
		private array $principalInfo,
		private readonly FolderManager $folderManager,
		private readonly IRootFolder $rootFolder,
		private readonly IUser $user,
	) {
	}

	public function delete(): never {
		throw new Forbidden();
	}

	public function getName(): string {
		[, $name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	public function setName($name): never {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function createFile($name, $data = null): never {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	public function createDirectory($name): never {
		throw new Forbidden('Permission denied to create folders in this folder');
	}

	private function getFolder(string $name): ?FolderDefinition {
		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return null;
		}

		$folders = $this->folderManager->getFoldersForUser($this->user);
		foreach ($folders as $folder) {
			if (basename($folder->mountPoint) === $name) {
				return $folder;
			}
		}

		return null;
	}

	private function getDirectoryForFolder(FolderDefinition $folder): GroupFolderNode {
		$userHome = '/' . $this->user->getUID() . '/files';
		$node = $this->rootFolder->get($userHome . '/' . $folder->mountPoint);

		$view = Filesystem::getView();
		if ($view === null) {
			throw new RuntimeException('Unable to create view.');
		}

		return new GroupFolderNode($view, $node, $folder->id);
	}

	public function getChild($name): GroupFolderNode {
		$folder = $this->getFolder($name);
		if ($folder) {
			return $this->getDirectoryForFolder($folder);
		}

		throw new NotFound();
	}

	/**
	 * @return GroupFolderNode[]
	 */
	public function getChildren(): array {
		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return [];
		}

		$folders = $this->folderManager->getFoldersForUser($this->user);

		// Filter out non top-level folders
		$folders = array_filter($folders, fn (FolderDefinition $folder): bool => !str_contains($folder->mountPoint, '/'));

		return array_map($this->getDirectoryForFolder(...), $folders);
	}

	public function childExists($name): bool {
		return $this->getFolder($name) !== null;
	}

	public function getLastModified(): int {
		return 0;
	}
}
