<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Listeners\NodeRenamedListener;

use OCA\GroupFolders\Listeners\NodeRenamedListener;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Trash\TrashManager;
use OCP\EventDispatcher\Event;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class NodeRenamedListenerTest extends TestCase {
	private TrashManager&MockObject $trashManager;
	private NodeRenamedListener $listener;
	private GroupFolderStorage&MockObject $sourceParentStorage;
	private Folder&MockObject $sourceParent;
	private File&MockObject $source;
	private GroupFolderStorage&MockObject $targetStorage;
	/** @var (Folder&MockObject)|(File&MockObject) $target */
	private MockObject $target;
	private NodeRenamedEvent&MockObject $event;

	protected function setUp(): void {
		parent::setUp();

		$this->trashManager = $this->createMock(TrashManager::class);

		$this->listener = new NodeRenamedListener($this->trashManager);

		$this->sourceParentStorage = $this->createMock(GroupFolderStorage::class);
		$this->sourceParentStorage
			->expects($this->any())
			->method('getFolderId')
			->willReturn(1);

		$this->sourceParent = $this->createMock(Folder::class);
		$this->sourceParent
			->expects($this->any())
			->method('getStorage')
			->willReturn($this->sourceParentStorage);

		$this->source = $this->createMock(File::class);
		$this->source
			->expects($this->any())
			->method('getParent')
			->willReturn($this->sourceParent);
		$this->source
			->expects($this->any())
			->method('getName')
			->willReturn('test.txt');

		$this->targetStorage = $this->createMock(GroupFolderStorage::class);
		$this->targetStorage
			->expects($this->any())
			->method('getFolderId')
			->willReturn(2);

		$this->event = $this->createMock(NodeRenamedEvent::class);
		$this->event
			->expects($this->any())
			->method('getSource')
			->willReturn($this->source);
		$this->event
			->expects($this->any())
			->method('getTarget')
			->willReturnCallback(fn (): MockObject => $this->target);
	}

	public function testHandleInvalid(): void {
		$event = $this
			->getMockBuilder(Event::class)
			->addMethods(['getSource'])
			->getMock();
		$event
			->expects($this->never())
			->method('getSource');

		/** @psalm-suppress InvalidArgument on purpose */
		$this->listener->handle($event);
	}

	public function testHandle(): void {
		$this->sourceParent
			->expects($this->once())
			->method('getInternalPath')
			->willReturn('abc');

		$this->sourceParentStorage
			->expects($this->once())
			->method('instanceOfStorage')
			->willReturn(true);

		$this->target = $this->createMock(Folder::class);
		$this->target
			->expects($this->once())
			->method('getStorage')
			->willReturn($this->targetStorage);
		$this->target
			->expects($this->once())
			->method('getInternalPath')
			->willReturn('def');

		$this->targetStorage
			->expects($this->once())
			->method('instanceOfStorage')
			->willReturn(true);

		$this->trashManager
			->expects($this->once())
			->method('updateTrashedChildren')
			->with(1, 2, 'abc/test.txt', 'def');

		$this->listener->handle($this->event);
	}

	public function testHandleInRootFolder(): void {
		$this->sourceParent
			->expects($this->once())
			->method('getInternalPath')
			->willReturn('');

		$this->sourceParentStorage
			->expects($this->once())
			->method('instanceOfStorage')
			->willReturn(true);

		$this->target = $this->createMock(Folder::class);
		$this->target
			->expects($this->once())
			->method('getStorage')
			->willReturn($this->targetStorage);
		$this->target
			->expects($this->once())
			->method('getInternalPath')
			->willReturn('def');

		$this->targetStorage
			->expects($this->once())
			->method('instanceOfStorage')
			->willReturn(true);

		$this->trashManager
			->expects($this->once())
			->method('updateTrashedChildren')
			->with(1, 2, 'test.txt', 'def');

		$this->listener->handle($this->event);
	}

	public function testHandleTargetNotAFolder(): void {
		$this->target = $this->createMock(File::class);

		$this->trashManager
			->expects($this->never())
			->method('updateTrashedChildren');

		$this->listener->handle($this->event);
	}

	public function testHandleSourceNotAGroupfolder(): void {
		$this->target = $this->createMock(Folder::class);
		$this->target
			->expects($this->once())
			->method('getStorage')
			->willReturn($this->targetStorage);

		$this->targetStorage
			->expects($this->once())
			->method('instanceOfStorage')
			->willReturn(true);

		$this->sourceParentStorage
			->expects($this->once())
			->method('instanceOfStorage')
			->willReturn(false);

		$this->trashManager
			->expects($this->never())
			->method('updateTrashedChildren');

		$this->listener->handle($this->event);
	}


	public function testHandleTargetNotAGroupfolder(): void {
		$this->target = $this->createMock(Folder::class);
		$this->target
			->expects($this->once())
			->method('getStorage')
			->willReturn($this->targetStorage);

		$this->targetStorage
			->expects($this->once())
			->method('instanceOfStorage')
			->willReturn(false);

		$this->trashManager
			->expects($this->never())
			->method('updateTrashedChildren');

		$this->listener->handle($this->event);
	}

}
