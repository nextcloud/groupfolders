<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Event;

use OCA\GroupFolders\Event\GroupVersionsExpireDeleteVersionEvent;
use OCA\GroupFolders\Versions\GroupVersion;
use Test\TestCase;

class GroupVersionsExpireDeleteVersionEventTest extends TestCase {
	public function testProperties(): void {
		$version = $this->createMock(GroupVersion::class);
		$version
			->expects($this->once())
			->method('getFolderId')
			->willReturn(123);

		$event = new GroupVersionsExpireDeleteVersionEvent($version);
		$this->assertEquals(123, $event->version->getFolderId());
	}
}
