<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Versions;

use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\Version;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IUser;

class GroupVersion extends Version {
	public function __construct(
		int $timestamp,
		int $revisionId,
		string $name,
		float|int $size,
		string $mimetype,
		string $path,
		FileInfo $sourceFileInfo,
		IVersionBackend $backend,
		IUser $user,
		array $metadata,
		private File $versionFile,
		private int $folderId,
	) {
		parent::__construct($timestamp, $revisionId, $name, $size, $mimetype, $path, $sourceFileInfo, $backend, $user, $metadata);
	}

	public function getVersionFile(): File {
		return $this->versionFile;
	}

	public function getFolderId(): int {
		return $this->folderId;
	}
}
