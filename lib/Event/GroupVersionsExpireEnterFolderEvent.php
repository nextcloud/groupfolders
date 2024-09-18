<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Event;

use OCP\EventDispatcher\Event;

/**
 * Event fired when versions inside a folder are going to be expired.
 */
class GroupVersionsExpireEnterFolderEvent extends Event {
	public function __construct(
		public array $folder,
	) {
		parent::__construct();
	}
}
