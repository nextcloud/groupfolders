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

/**
 * WebDAV collection representing a user's group folders home directory.
 *
 * Serves as a container for all group folders accessible to a specific user,
 * providing read-only access to the list of group folders they can access.
 * Each child node is a GroupFolderNode representing an individual group folder.
 */
class GroupFoldersHome implements ICollection {
	public function __construct(
		private array $principalInfo,
		private readonly FolderManager $folderManager,
		private readonly IRootFolder $rootFolder,
		private readonly IUser $user,
	) {
	}

	public function delete(): never {
		throw new Forbidden('Permission denied to delete this folder');
	}

	public function getName(): string {
		[, $name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	public function setName(string $name): never {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function createFile(string $name, $data = null): never {
		throw new Forbidden('Permission denied to create files in this folder');
	}

	public function createDirectory(string $name): never {
		throw new Forbidden('Permission denied to create folders in this folder');
	}

	private function getFolder(string $name): ?FolderDefinition {
		$folders = $this->folderManager->getFoldersForUser($this->user);
		foreach ($folders as $folder) {
			if (basename($folder->mountPoint) === $name) {
				return $folder;
			}
		}

		return null;
	}

	/**
	 * Creates a GroupFolderNode for the given folder definition.
	 *
	 * @throws RuntimeException If the filesystem view cannot be obtained
	 */
	private function getDirectoryForFolder(FolderDefinition $folder): GroupFolderNode {
		$userHome = '/' . $this->user->getUID() . '/files';
		$node = $this->rootFolder->get($userHome . '/' . $folder->mountPoint);

		$view = Filesystem::getView();
		if ($view === null) {
			throw new RuntimeException('Unable to create view.');
		}

		return new GroupFolderNode($view, $node, $folder->id);
	}

	public function getChild(string $name): GroupFolderNode {
		$folder = $this->getFolder($name);
		if ($folder !== null) {
			return $this->getDirectoryForFolder($folder);
		}

		throw new NotFound();
	}

	/**
	 * @return GroupFolderNode[]
	 */
	public function getChildren(): array {
		$folders = $this->folderManager->getFoldersForUser($this->user);

		// Filter out non top-level folders
		$folders = array_filter($folders, fn (FolderDefinition $folder): bool => !str_contains($folder->mountPoint, '/'));

		return array_map($this->getDirectoryForFolder(...), $folders);
	}

	public function childExists(string $name): bool {
		return $this->getFolder($name) !== null;
	}

	public function getLastModified(): int {
		return 0;
	}
}
