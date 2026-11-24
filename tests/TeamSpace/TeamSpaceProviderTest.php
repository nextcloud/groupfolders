<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\TeamSpace;

use OCA\GroupFolders\TeamSpace\TeamSpaceProvider;
use OCA\GroupFolders\TeamSpace\TeamSpaceService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Teams\TeamActivityScope;
use OCP\Teams\TeamFolder;
use OCP\Teams\TeamResource;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TeamSpaceProviderTest extends TestCase {
	private TeamSpaceService&MockObject $service;
	private IURLGenerator&MockObject $urlGenerator;
	private TeamSpaceProvider $provider;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(TeamSpaceService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->provider = new TeamSpaceProvider(
			$this->service,
			$this->createMock(IL10N::class),
			$this->urlGenerator,
		);
	}

	public function testGetSharedWithReturnsAllFoldersAssignedToTeam(): void {
		$this->service->expects($this->once())
			->method('getTeamSpaceForCircle')
			->with('team-1')
			->willReturn(new TeamFolder(42, 'Engineering'));
		$this->service->expects($this->once())
			->method('getGroupFoldersForCircle')
			->with('team-1')
			->willReturn([
				new TeamFolder(42, 'Engineering'),
				new TeamFolder(43, 'Shared projects'),
			]);
		$this->urlGenerator->expects($this->exactly(2))
			->method('linkToRouteAbsolute')
			->willReturnMap([
				['circles.page.indexpath', ['path' => 'team/team-1'], 'https://cloud.example/index.php/apps/circles/teams/team/team-1'],
				['files.view.index', ['dir' => '/Shared projects'], 'https://cloud.example/apps/files/?dir=/Shared%20projects'],
			]);

		$resources = $this->provider->getSharedWith('team-1');

		$this->assertCount(2, $resources);
		$this->assertSame('42', $resources[0]->getId());
		$this->assertSame('Engineering', $resources[0]->getLabel());
		$this->assertSame('https://cloud.example/index.php/apps/circles/teams/team/team-1', $resources[0]->getUrl());
		$this->assertSame('43', $resources[1]->getId());
		$this->assertSame('Shared projects', $resources[1]->getLabel());
		$this->assertSame('https://cloud.example/apps/files/?dir=/Shared%20projects', $resources[1]->getUrl());
	}

	public function testGetActivityScopesReturnsGroupFolderRoots(): void {
		$resources = [
			new TeamResource($this->provider, '42', 'Engineering', 'https://cloud.example/engineering'),
			new TeamResource($this->provider, '43', 'Shared projects', 'https://cloud.example/projects'),
		];
		$this->service->expects($this->once())
			->method('getRootFileIds')
			->with([42, 43])
			->willReturn([420, 430]);

		$scopes = $this->provider->getActivityScopes($resources, 'alice');

		$this->assertCount(1, $scopes);
		$this->assertInstanceOf(TeamActivityScope::class, $scopes[0]);
		$this->assertSame('files', $scopes[0]->getObjectType());
		$this->assertSame([420, 430], $scopes[0]->getObjectIds());
	}

	public function testGetLinkableTeamFoldersDelegatesToService(): void {
		$folders = [new TeamFolder(42, 'Engineering')];
		$this->service->expects($this->once())
			->method('getLinkableGroupFoldersForCircle')
			->with('team-1')
			->willReturn($folders);

		$this->assertSame($folders, $this->provider->getLinkableTeamFolders('team-1'));
	}

	public function testLinkTeamFolderLinksAndReturnsFolder(): void {
		$folder = new TeamFolder(42, 'Engineering');
		$this->service->expects($this->once())
			->method('linkExistingTeamSpace')
			->with('team-1', 42);
		$this->service->expects($this->once())
			->method('getTeamSpaceForCircle')
			->with('team-1')
			->willReturn($folder);

		$this->assertSame($folder, $this->provider->linkTeamFolder('team-1', 42));
	}

	public function testUpdateTeamFolderQuotaDelegatesToService(): void {
		$folder = new TeamFolder(42, 'Engineering');
		$this->service->expects($this->once())
			->method('updateTeamSpaceQuota')
			->with('team-1', 1024)
			->willReturn($folder);

		$this->assertSame($folder, $this->provider->updateTeamFolderQuota('team-1', 1024));
	}

	public function testIsSharedWithTeamChecksAllFoldersAssignedToTeam(): void {
		$this->service->expects($this->exactly(2))
			->method('getGroupFoldersForCircle')
			->with('team-1')
			->willReturn([
				new TeamFolder(42, 'Engineering'),
				new TeamFolder(43, 'Shared projects'),
			]);

		$this->assertTrue($this->provider->isSharedWithTeam('team-1', '43'));
		$this->assertFalse($this->provider->isSharedWithTeam('team-1', '44'));
	}
}
