<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\TeamSpace;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\TeamSpace\TeamSpaceService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;
use OCP\Teams\Team;

class TeamSpaceServiceTest extends TestCase {
	private FolderManager&MockObject $folderManager;
	private TeamSpaceService $service;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->folderManager = $this->createMock(FolderManager::class);
		$this->service = new TeamSpaceService(
			$this->folderManager,
			$this->createMock(LoggerInterface::class),
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

	public function testPickBaseNameFallsBackToTeamId(): void {
		$this->assertSame('team-1', $this->service->pickBaseName(new Team('team-1', '', null)));
	}

	public function testSanitizeMountPointStripsControlCharsAndSeparators(): void {
		$this->assertSame('Engineering', $this->service->sanitizeMountPoint("Engi\nnee/rin\\g"));
	}
}
