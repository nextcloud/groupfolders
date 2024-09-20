<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Model\Circle;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Listeners\CircleDestroyedEventListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CircleDestroyedEventListenerTest extends TestCase {
	private FolderManager&MockObject $folderManager;
	private CircleDestroyedEventListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->folderManager = $this->createMock(FolderManager::class);

		$this->listener = new CircleDestroyedEventListener($this->folderManager);
	}

	public function testHandleInvalid(): void {
		$event = $this->createMock(Event::class);

		$this->folderManager
			->expects($this->never())
			->method('deleteCircle');

		/** @psalm-suppress InvalidArgument on purpose */
		$this->listener->handle($event);
	}

	public function testHandle(): void {
		$circle = $this->createMock(Circle::class);
		$circle
			->expects($this->once())
			->method('getSingleId')
			->willReturn('123');

		$event = $this->createMock(CircleDestroyedEvent::class);
		$event
			->expects($this->once())
			->method('getCircle')
			->willReturn($circle);

		$this->folderManager
			->expects($this->once())
			->method('deleteCircle')
			->with('123');

		$this->listener->handle($event);
	}
}
