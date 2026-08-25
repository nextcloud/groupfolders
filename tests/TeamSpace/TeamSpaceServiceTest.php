<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\TeamSpace;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\TeamSpace\TeamSpaceService;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IScanner;
use OCP\Files\Storage\IStorage;
use OCP\Teams\Team;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class TeamSpaceServiceTest extends TestCase {
	private FolderManager&MockObject $folderManager;
	private FolderStorageManager&MockObject $folderStorageManager;
	private TeamSpaceService $service;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->folderManager = $this->createMock(FolderManager::class);
		$this->folderStorageManager = $this->createMock(FolderStorageManager::class);
		$this->service = new TeamSpaceService(
			$this->folderManager,
			$this->folderStorageManager,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testCreateTeamSpaceStoresOnlyTeamSpaceLink(): void {
		$teamSpace = $this->createTeamSpaceFolder();
		$storage = $this->createMock(IStorage::class);
		$scanner = $this->createMock(IScanner::class);
		$this->folderManager->method('createFolder')->with('Engineering')->willReturn(42);
		$this->folderManager->method('getFolder')->with(42)->willReturn($teamSpace);
		$this->folderStorageManager->expects($this->once())->method('getBaseStorageForFolder')->with(42, false, $teamSpace)->willReturn($storage);
		$storage->expects($this->once())->method('is_dir')->with('.system')->willReturn(false);
		$storage->expects($this->once())->method('mkdir')->with('.system')->willReturn(true);
		$storage->expects($this->once())->method('getScanner')->willReturn($scanner);
		$scannedPaths = [];
		$scanner->expects($this->once())->method('scan')->willReturnCallback(
			static function (string $path) use (&$scannedPaths): void {
				$scannedPaths[] = $path;
			},
		);
		$this->folderManager->expects($this->once())->method('setFolderQuota')->with(42, 1024);
		$this->folderManager->expects($this->once())->method('addApplicableGroup')->with(42, 'team-1');
		$this->folderManager->expects($this->once())->method('setManageACL')->with(42, 'circle', 'team-1', true);
		$this->folderManager->expects($this->once())->method('setTeamCircleId')->with(42, 'team-1');

		$this->assertSame(42, $this->service->createTeamSpace('team-1', 'Engineering', 1024));
		$this->assertSame(['.system'], $scannedPaths);
	}

	public function testCreateTeamSpaceDoesNotRecreateExistingAppDirectory(): void {
		$teamSpace = $this->createTeamSpaceFolder();
		$storage = $this->createMock(IStorage::class);
		$this->folderManager->method('createFolder')->with('Engineering')->willReturn(42);
		$this->folderManager->method('getFolder')->with(42)->willReturn($teamSpace);
		$this->folderStorageManager->method('getBaseStorageForFolder')->with(42, false, $teamSpace)->willReturn($storage);
		$storage->expects($this->once())->method('is_dir')->with('.system')->willReturn(true);
		$storage->expects($this->never())->method('mkdir');
		$storage->expects($this->never())->method('getScanner');

		$this->service->createTeamSpace('team-1', 'Engineering');
	}

	public function testCreateTeamSpaceRollsBackWhenAppDirectoryCannotBeCreated(): void {
		$teamSpace = $this->createTeamSpaceFolder();
		$storage = $this->createMock(IStorage::class);
		$this->folderManager->method('createFolder')->with('Engineering')->willReturn(42);
		$this->folderManager->method('getFolder')->with(42)->willReturn($teamSpace);
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(null);
		$this->folderStorageManager->method('getBaseStorageForFolder')->with(42, false, $teamSpace)->willReturn($storage);
		$storage->method('is_dir')->with('.system')->willReturn(false);
		$storage->method('mkdir')->with('.system')->willReturn(false);
		$this->folderManager->expects($this->once())->method('clearTeamCircleId')->with(42);
		$this->folderManager->expects($this->once())->method('removeFolder')->with(42);

		$this->expectException(\RuntimeException::class);
		$this->service->createTeamSpace('team-1', 'Engineering');
	}

	public function testUpgradeUsesPublicTeamValue(): void {
		$team = new Team('team-1', 'Engineering', null);
		$teamSpace = $this->createTeamSpaceFolder();
		$storage = $this->createMock(IStorage::class);
		$scanner = $this->createMock(IScanner::class);
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(null);
		$this->folderManager->method('mountPointExists')->willReturn(false);
		$this->folderManager->expects($this->once())->method('createFolder')->with('Engineering')->willReturn(42);
		$this->folderManager->method('getFolder')->with(42)->willReturn($teamSpace);
		$this->folderStorageManager->method('getBaseStorageForFolder')->with(42, false, $teamSpace)->willReturn($storage);
		$storage->method('is_dir')->with('.system')->willReturn(false);
		$storage->method('mkdir')->with('.system')->willReturn(true);
		$storage->method('getScanner')->willReturn($scanner);
		$this->folderManager->expects($this->once())->method('setTeamCircleId')->with(42, 'team-1');

		$this->assertSame(42, $this->service->upgradeTeamSpace($team));
	}

	public function testGetTeamSpaceForCircleDoesNotRecreateExistingAppDirectory(): void {
		$teamSpace = $this->createTeamSpaceFolder();
		$storage = $this->createMock(IStorage::class);
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(42);
		$this->folderManager->method('getFolder')->with(42)->willReturn($teamSpace);
		$this->folderStorageManager->expects($this->once())->method('getBaseStorageForFolder')->with(42, false, $teamSpace)->willReturn($storage);
		$storage->expects($this->once())->method('is_dir')->with('.system')->willReturn(true);
		$storage->expects($this->never())->method('mkdir');
		$storage->expects($this->never())->method('getScanner');

		$this->assertSame(42, $this->service->getTeamSpaceForCircle('team-1')?->getId());
	}

	public function testUnlinkKeepsFolderAndClearsTeamLink(): void {
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(42);
		$this->folderManager->expects($this->once())->method('clearTeamCircleId')->with(42);
		$this->folderManager->expects($this->once())->method('removeApplicableGroup')->with(42, 'team-1');
		$this->folderManager->expects($this->never())->method('removeFolder');

		$this->assertSame(42, $this->service->unlinkTeamSpace('team-1'));
	}

	public function testUpdateTeamSpaceQuotaReturnsUpdatedFolder(): void {
		$this->folderManager->expects($this->once())
			->method('getFolderIdByTeamCircleId')
			->with('team-1')
			->willReturn(42);
		$this->folderManager->expects($this->once())
			->method('setFolderQuota')
			->with(42, 1024);
		$this->folderManager->expects($this->once())
			->method('getFolder')
			->with(42)
			->willReturn(new FolderDefinition(42, 'Engineering', 1024, false, false, 1, 2, [], 'team-1'));

		$folder = $this->service->updateTeamSpaceQuota('team-1', 1024);

		$this->assertSame(42, $folder->getId());
		$this->assertSame('Engineering', $folder->getMountPoint());
	}

	public function testGetGroupFoldersForCircleReturnsAllAssignedFolders(): void {
		$this->folderManager->expects($this->once())
			->method('getFoldersForCircle')
			->with('team-1')
			->willReturn([
				new FolderDefinition(42, 'Engineering', 0, false, false, 1, 2, []),
				new FolderDefinition(43, 'Shared projects', 0, false, false, 3, 4, []),
			]);

		$folders = $this->service->getGroupFoldersForCircle('team-1');

		$this->assertSame(42, $folders[0]->getId());
		$this->assertSame('Engineering', $folders[0]->getMountPoint());
		$this->assertSame(43, $folders[1]->getId());
		$this->assertSame('Shared projects', $folders[1]->getMountPoint());
	}

	public function testGetLinkableGroupFoldersRequiresExclusiveTeamAssignment(): void {
		$this->folderManager->expects($this->once())
			->method('getFoldersForCircle')
			->with('team-1')
			->willReturn([
				new FolderDefinition(42, 'Engineering', 0, false, false, 1, 2, []),
				new FolderDefinition(43, 'Shared projects', 0, false, false, 3, 4, []),
			]);
		$this->folderManager->expects($this->exactly(2))
			->method('isExclusivelyAssignedToCircle')
			->willReturnCallback(static fn (int $folderId, string $circleId): bool => $folderId === 42 && $circleId === 'team-1');

		$folders = $this->service->getLinkableGroupFoldersForCircle('team-1');

		$this->assertCount(1, $folders);
		$this->assertSame(42, $folders[0]->getId());
	}

	public function testLinkExistingTeamSpaceRequiresExclusiveTeamAssignment(): void {
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(null);
		$this->folderManager->method('getFoldersForCircle')->with('team-1')->willReturn([
			new FolderDefinition(42, 'Engineering', 0, false, false, 1, 2, []),
		]);
		$this->folderManager->expects($this->once())
			->method('isExclusivelyAssignedToCircle')
			->with(42, 'team-1')
			->willReturn(true);
		$this->folderManager->expects($this->once())
			->method('setTeamCircleId')
			->with(42, 'team-1');

		$this->service->linkExistingTeamSpace('team-1', 42);
	}

	public function testLinkExistingTeamSpaceRejectsNonExclusiveAssignment(): void {
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(null);
		$this->folderManager->method('getFoldersForCircle')->with('team-1')->willReturn([
			new FolderDefinition(42, 'Engineering', 0, false, false, 1, 2, []),
		]);
		$this->folderManager->expects($this->once())
			->method('isExclusivelyAssignedToCircle')
			->with(42, 'team-1')
			->willReturn(false);
		$this->folderManager->expects($this->never())->method('setTeamCircleId');

		$this->expectException(\InvalidArgumentException::class);
		$this->service->linkExistingTeamSpace('team-1', 42);
	}

	public function testPickBaseNameUsesDisplayName(): void {
		$this->assertSame('Engineering', $this->service->pickBaseName(new Team('team-1', 'Engineering', null)));
	}

	public function testSanitizeMountPointStripsControlCharsAndSeparators(): void {
		$this->assertSame('Engineering', $this->service->sanitizeMountPoint("Engi\nnee/rin\\g"));
	}

	private function createTeamSpaceFolder(): FolderWithMappingsAndCache {
		return new FolderWithMappingsAndCache(
			42,
			'Engineering',
			0,
			false,
			false,
			1,
			99,
			[],
			[],
			[],
			$this->createMock(ICacheEntry::class),
		);
	}
}
