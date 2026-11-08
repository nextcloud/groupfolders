<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\DAV;

use OC\Files\Filesystem;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountPathResolver;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\Server;
use RuntimeException;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

class GroupFoldersHome implements ICollection {
	/**
	 * @param array{uri: string} $principalInfo
	 */
	public function __construct(
		private array $principalInfo,
		private readonly FolderManager $folderManager,
		private readonly IRootFolder $rootFolder,
		private readonly IUser $user,
	) {
	}

	#[\Override]
	public function delete(): never {
		throw new Forbidden();
	}

	#[\Override]
	public function getName(): string {
		/** @var string $name */
		[, $name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	#[\Override]
	public function setName($name): never {
		throw new Forbidden('Permission denied to rename this folder');
	}

	#[\Override]
	public function createFile($name, $data = null): never {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	#[\Override]
	public function createDirectory($name): never {
		throw new Forbidden('Permission denied to create folders in this folder');
	}

	/**
	 * The folders of the user with the paths (relative to the user's files) they are mounted at.
	 *
	 * @return array<int, array{folder: FolderDefinitionWithPermissions, path: string}>
	 */
	private function getMountedFolders(): array {
		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return [];
		}

		$folders = $this->folderManager->getFoldersForUser($this->user);
		$mountPaths = Server::get(MountPathResolver::class)->resolveMountPaths($folders);

		$mounted = [];
		foreach ($folders as $folder) {
			if (isset($mountPaths[$folder->id])) {
				$mounted[$folder->id] = ['folder' => $folder, 'path' => $mountPaths[$folder->id]];
			}
		}

		return $mounted;
	}

	/**
	 * @return array{folder: FolderDefinitionWithPermissions, path: string}|null
	 */
	private function getFolder(string $name): ?array {
		foreach ($this->getMountedFolders() as $mounted) {
			if (basename($mounted['path']) === $name) {
				return $mounted;
			}
		}

		return null;
	}

	/**
	 * @param array{folder: FolderDefinitionWithPermissions, path: string} $mounted
	 */
	private function getDirectoryForFolder(array $mounted): GroupFolderNode {
		$userHome = '/' . $this->user->getUID() . '/files';
		$node = $this->rootFolder->get($userHome . '/' . $mounted['path']);

		$view = Filesystem::getView();
		if ($view === null) {
			throw new RuntimeException('Unable to create view.');
		}

		return new GroupFolderNode($view, $node, $mounted['folder']->id);
	}

	#[\Override]
	public function getChild($name): GroupFolderNode {
		$mounted = $this->getFolder($name);
		if ($mounted) {
			return $this->getDirectoryForFolder($mounted);
		}

		throw new NotFound();
	}

	/**
	 * @return GroupFolderNode[]
	 */
	#[\Override]
	public function getChildren(): array {
		$mounted = array_values($this->getMountedFolders());

		usort($mounted, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);

		$current = '';
		$leafFolders = [];
		foreach ($mounted as $item) {
			if (!str_starts_with($item['path'], $current . '/')) {
				$leafFolders[] = $item;
				$current = $item['path'];
			}
		}

		return array_map($this->getDirectoryForFolder(...), $leafFolders);
	}

	#[\Override]
	public function childExists($name): bool {
		return $this->getFolder($name) !== null;
	}

	#[\Override]
	public function getLastModified(): int {
		return 0;
	}
}
