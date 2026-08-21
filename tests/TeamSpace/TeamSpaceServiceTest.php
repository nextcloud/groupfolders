<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\TeamSpace;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\TeamSpace\TeamSpaceService;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IUserMountCache;
use OCP\Teams\Team;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class TeamSpaceServiceTest extends TestCase {
	private FolderManager&MockObject $folderManager;
	private IUserMountCache&MockObject $userMountCache;
	private TeamSpaceService $service;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->folderManager = $this->createMock(FolderManager::class);
		$this->userMountCache = $this->createMock(IUserMountCache::class);
		$this->service = new TeamSpaceService(
			$this->folderManager,
			$this->createMock(LoggerInterface::class),
			$this->userMountCache,
		);
	}

	public function testCreateTeamSpaceStoresOnlyTeamSpaceLink(): void {
		$this->folderManager->method('createFolder')->with('Engineering')->willReturn(42);
		$this->folderManager->expects($this->once())->method('setFolderQuota')->with(42, 1024);
		$this->folderManager->expects($this->once())->method('addApplicableGroup')->with(42, 'team-1');
		$this->folderManager->expects($this->once())->method('setManageACL')->with(42, 'circle', 'team-1', true);
		$this->folderManager->expects($this->once())->method('setTeamCircleId')->with(42, 'team-1');

		$this->assertSame(42, $this->service->createTeamSpace('team-1', 'Engineering', 1024));
	}

	public function testUpgradeUsesPublicTeamValue(): void {
		$team = new Team('team-1', 'Engineering', null);
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(null);
		$this->folderManager->method('mountPointExists')->willReturn(false);
		$this->folderManager->expects($this->once())->method('createFolder')->with('Engineering')->willReturn(42);
		$this->folderManager->expects($this->once())->method('setTeamCircleId')->with(42, 'team-1');

		$this->assertSame(42, $this->service->upgradeTeamSpace($team));
	}

	public function testUnlinkKeepsFolderAndClearsTeamLink(): void {
		$this->folderManager->method('getFolderIdByTeamCircleId')->with('team-1')->willReturn(42);
		$this->folderManager->expects($this->once())->method('clearTeamCircleId')->with(42);
		$this->folderManager->expects($this->once())->method('removeApplicableGroup')->with(42, 'team-1');
		$this->folderManager->expects($this->never())->method('removeFolder');

		$this->assertSame(42, $this->service->unlinkTeamSpace('team-1'));
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

	public function testPickBaseNameUsesDisplayName(): void {
		$this->assertSame('Engineering', $this->service->pickBaseName(new Team('team-1', 'Engineering', null)));
	}

	public function testSanitizeMountPointStripsControlCharsAndSeparators(): void {
		$this->assertSame('Engineering', $this->service->sanitizeMountPoint("Engi\nnee/rin\\g"));
	}

	public function testGetCircleIdsForFileResolvesThroughTheContainingFolder(): void {
		$this->userMountCache->method('getMountsForFileId')
			->with(1688)
			->willReturn([$this->mount(MountProvider::class, 1686)]);
		$this->folderManager->method('getFolderIdByRootId')->with(1686)->willReturn(3);
		$this->folderManager->method('getCircleIdsForFolder')->with(3)->willReturn(['team-1']);

		$this->assertSame(['team-1'], $this->service->getCircleIdsForFile(1688));
	}

	public function testGetCircleIdsForFileIgnoresMountsFromOtherProviders(): void {
		$this->userMountCache->method('getMountsForFileId')
			->willReturn([$this->mount('OCA\\Files_External\\Config\\ConfigAdapter', 99)]);
		$this->folderManager->expects($this->never())->method('getFolderIdByRootId');

		$this->assertSame([], $this->service->getCircleIdsForFile(1688));
	}

	public function testGetCircleIdsForFileIgnoresMountsWithoutAFolder(): void {
		$this->userMountCache->method('getMountsForFileId')
			->willReturn([$this->mount(MountProvider::class, 1686)]);
		$this->folderManager->method('getFolderIdByRootId')->willReturn(null);
		$this->folderManager->expects($this->never())->method('getCircleIdsForFolder');

		$this->assertSame([], $this->service->getCircleIdsForFile(1688));
	}

	public function testGetCircleIdsForFileReturnsEachTeamOnce(): void {
		$this->userMountCache->method('getMountsForFileId')->willReturn([
			$this->mount(MountProvider::class, 1686),
			$this->mount(MountProvider::class, 1786),
		]);
		$this->folderManager->method('getFolderIdByRootId')->willReturnMap([[1686, 3], [1786, 4]]);
		$this->folderManager->method('getCircleIdsForFolder')->willReturnMap([
			[3, ['team-1', 'team-2']],
			[4, ['team-2']],
		]);

		$this->assertSame(['team-1', 'team-2'], $this->service->getCircleIdsForFile(1688));
	}

	private function mount(string $mountProvider, int $rootId): ICachedMountInfo&MockObject {
		$mount = $this->createMock(ICachedMountInfo::class);
		$mount->method('getMountProvider')->willReturn($mountProvider);
		$mount->method('getRootId')->willReturn($rootId);

		return $mount;
	}
}
