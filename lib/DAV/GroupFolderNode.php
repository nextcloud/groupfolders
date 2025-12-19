<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\DAV;

use OC\Files\View;
use OCA\DAV\Connector\Sabre\Directory;
use OCP\Files\FileInfo;

/**
 * WebDAV node representing a group folder directory.
 *
 * Extends the standard Directory node to track the associated group folder ID,
 * allowing the system to identify and apply group folder-specific permissions
 * and logic when accessed via WebDAV.
 */
class GroupFolderNode extends Directory {
	public function __construct(
		View $view,
		FileInfo $info,
		private readonly int $folderId,
	) {
		parent::__construct($view, $info);
	}

	public function getFolderId(): int {
		return $this->folderId;
	}
}
