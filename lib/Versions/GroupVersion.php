<?php declare(strict_types=1);
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

use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\Version;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IUser;

class GroupVersion extends Version {
	private $versionFile;

	private $folderId;

	public function __construct(
		int $timestamp,
		int $revisionId,
		string $name,
		int $size,
		string $mimetype,
		string $path,
		FileInfo $sourceFileInfo,
		IVersionBackend $backend,
		IUser $user,
		File $versionFile,
		int $folderId
	) {
		parent::__construct($timestamp, $revisionId, $name, $size, $mimetype, $path, $sourceFileInfo, $backend, $user);
		$this->versionFile = $versionFile;
		$this->folderId = $folderId;
	}

	public function getVersionFile(): File {
		return $this->versionFile;
	}

	public function getFolderId(): int {
		return $this->folderId;
	}
}
