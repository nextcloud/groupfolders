<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\DAV;

use OC\Files\Filesystem;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\Photos\Sabre\Album\AlbumsHome;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\IRootFolder;
use OCP\IUser;
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
	 * @return never
	 */
	public function delete() {
		throw new Forbidden();
	}

	public function getName(): string {
		[, $name] = \Sabre\Uri\split($this->principalInfo['uri']);
		return $name;
	}

	/**
	 * @return never
	 */
	public function setName($name) {
		throw new Forbidden('Permission denied to rename this folder');
	}

	public function createFile($name, $data = null) {
		throw new Forbidden('Not allowed to create files in this folder');
	}

	/**
	 * @return never
	 */
	public function createDirectory($name) {
		throw new Forbidden('Permission denied to create folders in this folder');
	}

	/**
	 * @param string $name
	 * @return array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry}|null
	 */
	private function getFolder(string $name): ?array {
		$folders = $this->folderManager->getFoldersForUser($this->user, $this->rootFolder->getMountPoint()->getNumericStorageId());
		foreach ($folders as $folder) {
			if (basename($folder['mount_point']) === $name) {
				return $folder;
			}
		}
		return null;
	}

	/**
	 * @param array{folder_id: int, mount_point: string, permissions: int, quota: int, acl: bool, rootCacheEntry: ?ICacheEntry} $folder
	 * @return GroupFolderNode
	 */
	private function getDirectoryForFolder(array $folder): GroupFolderNode {
		$userHome = "/" . $this->user->getUID() . "/files";
		$node = $this->rootFolder->get($userHome . "/" . $folder['mount_point']);
		return new GroupFolderNode(Filesystem::getView($userHome), $node, $folder['folder_id']);
	}

	public function getChild($name) {
		$folder = $this->getFolder($name);
		if ($folder) {
			return $this->getDirectoryForFolder($folder);
		}

		throw new NotFound();
	}

	/**
	 * @return (AlbumsHome)[]
	 */
	public function getChildren(): array {
		$folders = $this->folderManager->getFoldersForUser($this->user, $this->rootFolder->getMountPoint()->getNumericStorageId());
		return array_map([$this, 'getDirectoryForFolder'], $folders);
	}

	public function childExists($name): bool {
		return $this->getFolder($name) !== null;
	}

	public function getLastModified(): int {
		return 0;
	}
}
