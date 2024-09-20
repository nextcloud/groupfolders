<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Event;

use OCA\GroupFolders\Event\GroupVersionsExpireEnterFolderEvent;
use Test\TestCase;

class GroupVersionsExpireEnterFolderEventTest extends TestCase {
	public function testProperties(): void {
		$event = new GroupVersionsExpireEnterFolderEvent(['key' => 'value']);
		$this->assertEquals(['key' => 'value'], $event->folder);
	}
}
