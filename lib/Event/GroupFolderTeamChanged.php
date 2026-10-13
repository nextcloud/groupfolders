<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Event;

use OCP\EventDispatcher\Event;

/**
 * Event fired when a group or a team is added or removed to a groupfolder.
 */
class GroupFolderTeamChanged extends Event {
	public const string ADD = 'add';
	public const string REMOVE = 'remove';

	public function __construct(
		public int $groupFolderId,
		public string $change,
		public string $groupId,
		public string $circleId,
	) {
		parent::__construct();
	}

	public function getGroupFolderId(): int {
		return $this->groupFolderId;
	}

	public function getChange(): string {
		return $this->change;
	}

	public function getGroupId(): string {
		return $this->groupId;
	}

	public function getCircleId(): string {
		return $this->circleId;
	}
}
