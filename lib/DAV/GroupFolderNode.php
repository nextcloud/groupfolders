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

class GroupFolderNode extends Directory {
	private int $folderId;

	public function __construct(View $view, FileInfo $info, int $folderId) {
		parent::__construct($view, $info);
		$this->folderId = $folderId;
	}

	public function getFolderId(): int {
		return $this->folderId;
	}
}
