<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Versions;

use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\Version;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IUser;

class GroupVersion extends Version {
	/** @var File */
	private $versionFile;

	/** @var int */
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
