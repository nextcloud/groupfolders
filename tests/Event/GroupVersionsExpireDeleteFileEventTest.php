<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Event;

use OCA\GroupFolders\Event\GroupVersionsExpireDeleteFileEvent;
use Test\TestCase;

class GroupVersionsExpireDeleteFileEventTest extends TestCase {
	public function testProperties(): void {
		$event = new GroupVersionsExpireDeleteFileEvent(123);
		$this->assertEquals(123, $event->fileId);
	}
}
