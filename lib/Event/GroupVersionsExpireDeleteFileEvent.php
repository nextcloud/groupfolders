<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Event;

use OCP\EventDispatcher\Event;

/**
 * Event fired when versions for a deleted file are going to be expired.
 */
class GroupVersionsExpireDeleteFileEvent extends Event {
	public function __construct(
		public string|int $fileId,
	) {
		parent::__construct();
	}
}
