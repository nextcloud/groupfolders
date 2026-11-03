<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Event;

use OCA\Circles\Model\Circle;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCP\EventDispatcher\Event;

class GroupFolderCircleAddedEvent extends Event {
	public function __construct(
		private readonly FolderDefinition $folder,
		private readonly Circle $circle,
	) {
		parent::__construct();
	}

	public function getFolder(): FolderDefinition {
		return $this->folder;
	}

	public function getCircle(): Circle {
		return $this->circle;
	}
}
