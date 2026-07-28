<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Listeners\NodeRenamedListener;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Listeners\NodeRenamedListener;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\EventDispatcher\Event;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Test\TestCase;

class NodeRenamedListenerTest extends TestCase {
	private TrashBackend&MockObject $trashBackend;
	private VersionsBackend&MockObject $versionBackend;
	private NodeRenamedListener $listener;
	private GroupFolderStorage&MockObject $sourceParentStorage;
	private Folder&MockObject $sourceParent;
	private File&MockObject $source;
	private GroupFolderStorage&MockObject $targetStorage;
	/** @var (Folder&MockObject)|(File&MockObject) $target */
	private MockObject $target;
	private NodeRenamedEvent&MockObject $event;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->trashBackend = $this->createMock(TrashBackend::class);
		$this->versionBackend = $this->createMock(VersionsBackend::class);

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')
			->willReturnMap([
				[TrashBackend::class, $this->trashBackend],
				[VersionsBackend::class, $this->versionBackend],
			]);

		$this->listener = new NodeRenamedListener($container);

		$this->sourceParentStorage = $this->createMock(GroupFolderStorage::class);
		$this->sourceParentStorage
			->expects($this->any())
			->method('getFolderId')
			->willReturn(1);
		$this->sourceParentStorage
			->expects($this->any())
			->method('getFolder')
			->willReturn(new FolderDefinition(1, 'foo', 0, false, false, 0, 0, []));

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
		$this->targetStorage
			->expects($this->any())
			->method('getFolder')
			->willReturn(new FolderDefinition(2, 'foo', 0, false, false, 0, 0, []));

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

		$this->trashBackend
			->expects($this->once())
			->method('updateTrashedChildren')
			->with($this->sourceParentStorage, $this->targetStorage, 'abc/test.txt', 'def');

		$this->versionBackend
			->expects($this->once())
			->method('moveVersionsBetweenFolders');

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

		$this->trashBackend
			->expects($this->once())
			->method('updateTrashedChildren')
			->with($this->sourceParentStorage, $this->targetStorage, 'test.txt', 'def');

		$this->versionBackend
			->expects($this->once())
			->method('moveVersionsBetweenFolders');

		$this->listener->handle($this->event);
	}

	public function testHandleTargetNotAFolder(): void {
		$this->target = $this->createMock(File::class);
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
			->willReturn(true);

		$this->trashBackend
			->expects($this->never())
			->method('updateTrashedChildren');

		$this->versionBackend
			->expects($this->once())
			->method('moveVersionsBetweenFolders');

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

		$this->trashBackend
			->expects($this->never())
			->method('updateTrashedChildren');

		$this->versionBackend
			->expects($this->never())
			->method('moveVersionsBetweenFolders');

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

		$this->trashBackend
			->expects($this->never())
			->method('updateTrashedChildren');

		$this->versionBackend
			->expects($this->never())
			->method('moveVersionsBetweenFolders');

		$this->listener->handle($this->event);
	}

}
