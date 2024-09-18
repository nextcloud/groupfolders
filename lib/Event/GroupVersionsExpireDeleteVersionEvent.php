<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Event;

use OCA\GroupFolders\Versions\GroupVersion;
use OCP\EventDispatcher\Event;

/**
 * Event fired when a version is going to be expired.
 */
class GroupVersionsExpireDeleteVersionEvent extends Event {
	public function __construct(
		public GroupVersion $version,
	) {
		parent::__construct();
	}
}
