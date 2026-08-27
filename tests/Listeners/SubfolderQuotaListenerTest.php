<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Listeners;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\SubfolderQuotaManager;
use OCA\GroupFolders\Listeners\SubfolderQuotaListener;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCP\Exceptions\AbortedEventException;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Folder;
use OCP\Files\Storage\IStorage;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SubfolderQuotaListenerTest extends TestCase {
	private SubfolderQuotaManager&MockObject $subfolderQuotaManager;
	private SubfolderQuotaListener $listener;
	private GroupFolderStorage&MockObject $sourceStorage;
	private GroupFolderStorage&MockObject $targetStorage;
	private Folder&MockObject $source;
	private Folder&MockObject $target;
	private bool $targetAppliesSubfolderQuotas = true;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->subfolderQuotaManager = $this->createMock(SubfolderQuotaManager::class);
		$this->listener = new SubfolderQuotaListener($this->subfolderQuotaManager);

		$this->sourceStorage = $this->createMock(GroupFolderStorage::class);
		$this->sourceStorage
			->method('instanceOfStorage')
			->with(GroupFolderStorage::class)
			->willReturn(true);
		$this->sourceStorage
			->method('getFolder')
			->willReturn($this->getFolderDefinition());
		$this->sourceStorage
			->method('getFolderId')
			->willReturn(1);

		$this->targetStorage = $this->createMock(GroupFolderStorage::class);
		$this->targetStorage
			->method('instanceOfStorage')
			->with(GroupFolderStorage::class)
			->willReturn(true);
		$this->targetStorage
			->method('getFolderId')
			->willReturn(1);
		$this->targetStorage
			->method('getFolder')
			->willReturn($this->getFolderDefinition());
		$this->targetStorage
			->method('appliesSubfolderQuotas')
			->willReturnCallback(fn (): bool => $this->targetAppliesSubfolderQuotas);

		$this->source = $this->createMock(Folder::class);
		$this->source
			->method('getStorage')
			->willReturn($this->sourceStorage);
		$this->source
			->method('getId')
			->willReturn(42);
		$this->source
			->method('getPath')
			->willReturn('/team/source');

		$this->target = $this->createMock(Folder::class);
		$this->target
			->method('getPath')
			->willReturn('/team/renamed');
	}

	public function testKeepsQuotaWhenRenamingAConfiguredDirectChild(): void {
		$targetParent = $this->createMock(Folder::class);
		$targetParent
			->method('getId')
			->willReturn(10);
		$this->target
			->method('getStorage')
			->willReturn($this->targetStorage);
		$this->target
			->method('getParent')
			->willReturn($targetParent);

		$this->subfolderQuotaManager
			->expects($this->once())
			->method('hasSubfolderQuota')
			->with(1, 42)
			->willReturn(true);
		$this->subfolderQuotaManager
			->expects($this->never())
			->method('removeSubfolderQuota');

		$this->listener->handle($this->getBeforeRenameEvent($this->source, $this->target));

		$sourceAfterRename = $this->createMock(Folder::class);
		$sourceAfterRename
			->method('getPath')
			->willReturn('/team/source');
		$sourceAfterRename
			->expects($this->never())
			->method('getId');
		$this->listener->handle($this->getRenameEvent($sourceAfterRename, $this->target));
	}

	public function testRemovesQuotaWhenAConfiguredSubfolderLeavesTheTeamFolder(): void {
		$externalStorage = $this->createMock(IStorage::class);
		$externalStorage
			->method('instanceOfStorage')
			->with(GroupFolderStorage::class)
			->willReturn(false);
		$this->target
			->method('getStorage')
			->willReturn($externalStorage);

		$this->subfolderQuotaManager
			->expects($this->once())
			->method('hasSubfolderQuota')
			->with(1, 42)
			->willReturn(true);
		$this->subfolderQuotaManager
			->expects($this->once())
			->method('removeSubfolderQuota')
			->with(1, 42);

		$this->listener->handle($this->getBeforeRenameEvent($this->source, $this->target));

		$sourceAfterRename = $this->createMock(Folder::class);
		$sourceAfterRename
			->method('getPath')
			->willReturn('/team/source');
		$sourceAfterRename
			->expects($this->never())
			->method('getId');
		$this->listener->handle($this->getRenameEvent($sourceAfterRename, $this->target));
	}

	public function testPreventsMovingAConfiguredSubfolderIntoANestedPath(): void {
		$targetParent = $this->createMock(Folder::class);
		$targetParent
			->method('getId')
			->willReturn(99);
		$this->target
			->method('getStorage')
			->willReturn($this->targetStorage);
		$this->target
			->method('getParent')
			->willReturn($targetParent);

		$this->subfolderQuotaManager
			->expects($this->once())
			->method('hasSubfolderQuota')
			->with(1, 42)
			->willReturn(true);

		$this->expectException(AbortedEventException::class);
		$this->listener->handle($this->getBeforeRenameEvent($this->source, $this->target));
	}

	public function testAllowsMovingAConfiguredSubfolderToTrashStorage(): void {
		$this->targetAppliesSubfolderQuotas = false;
		$this->target
			->method('getStorage')
			->willReturn($this->targetStorage);

		$this->subfolderQuotaManager
			->expects($this->once())
			->method('hasSubfolderQuota')
			->with(1, 42)
			->willReturn(true);
		$this->subfolderQuotaManager
			->expects($this->once())
			->method('removeSubfolderQuota')
			->with(1, 42);

		$this->listener->handle($this->getBeforeRenameEvent($this->source, $this->target));

		$sourceAfterRename = $this->createMock(Folder::class);
		$sourceAfterRename
			->method('getPath')
			->willReturn('/team/source');
		$this->listener->handle($this->getRenameEvent($sourceAfterRename, $this->target));
	}

	public function testRemovesQuotaWhenConfiguredSubfolderIsDeleted(): void {
		$this->subfolderQuotaManager
			->expects($this->once())
			->method('removeSubfolderQuota')
			->with(1, 42);

		$event = $this->createMock(NodeDeletedEvent::class);
		$event
			->method('getNode')
			->willReturn($this->source);

		$this->listener->handle($event);
	}

	private function getFolderDefinition(): FolderDefinition {
		return new FolderDefinition(1, 'team', 0, false, false, 1, 10, []);
	}

	private function getBeforeRenameEvent(Folder $source, Folder $target): BeforeNodeRenamedEvent {
		$event = $this->createMock(BeforeNodeRenamedEvent::class);
		$event
			->method('getSource')
			->willReturn($source);
		$event
			->method('getTarget')
			->willReturn($target);

		return $event;
	}

	private function getRenameEvent(Folder $source, Folder $target): NodeRenamedEvent {
		$event = $this->createMock(NodeRenamedEvent::class);
		$event
			->method('getSource')
			->willReturn($source);
		$event
			->method('getTarget')
			->willReturn($target);

		return $event;
	}
}
