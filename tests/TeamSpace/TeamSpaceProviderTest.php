<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\TeamSpace;

use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountPathResolver;
use OCA\GroupFolders\TeamSpace\TeamSpaceProvider;
use OCA\GroupFolders\TeamSpace\TeamSpaceService;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Teams\TeamFolder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class TeamSpaceProviderTest extends TestCase {
	private TeamSpaceService&MockObject $service;
	private IURLGenerator&MockObject $urlGenerator;
	private FolderManager&MockObject $folderManager;
	private IUserSession&MockObject $userSession;
	private TeamSpaceProvider $provider;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->createMock(TeamSpaceService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->folderManager = $this->createMock(FolderManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->provider = $this->createProvider(null);
	}

	private function createProvider(?string $prefix): TeamSpaceProvider {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default): string => $key === MountPathResolver::CONFIG_KEY ? ($prefix ?? $default) : $default);

		return new TeamSpaceProvider(
			$this->service,
			$this->createMock(IL10N::class),
			$this->urlGenerator,
			$this->folderManager,
			new MountPathResolver($appConfig, $this->createMock(LoggerInterface::class)),
			$this->userSession,
		);
	}

	private function createFolder(int $id, string $mountPoint, bool $team): FolderDefinitionWithPermissions {
		return new FolderDefinitionWithPermissions($id, $mountPoint, -3, false, false, 1, $id, [], $this->createMock(ICacheEntry::class), Constants::PERMISSION_ALL, $team ? 'circle-' . $id : null);
	}

	/**
	 * @param list<FolderDefinitionWithPermissions> $folders
	 */
	private function loginWithFolders(array $folders): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession->method('getUser')->willReturn($user);
		$this->folderManager->method('getFoldersForUser')->with($user)->willReturn($folders);
	}

	public function testTeamFolderMountPointIsThePathOfTheCurrentUser(): void {
		$this->provider = $this->createProvider('TeamFolders');
		$this->loginWithFolders([
			$this->createFolder(42, 'Engineering', true),
			$this->createFolder(43, 'Project', true),
			$this->createFolder(44, 'TeamFolders/Project', false),
		]);
		$this->service->method('getTeamSpaceForCircle')->willReturnMap([
			['team-1', new TeamFolder(42, 'Engineering', 1024)],
			['team-2', new TeamFolder(43, 'Project')],
		]);
		$this->service->method('updateTeamSpaceQuota')->willReturn(new TeamFolder(42, 'Engineering', 2048));
		$this->service->method('getLinkableGroupFoldersForCircle')->willReturn([new TeamFolder(44, 'TeamFolders/Project')]);

		$folder = $this->provider->getTeamFolder('team-1');
		$this->assertNotNull($folder);
		$this->assertSame(42, $folder->getId());
		$this->assertSame('TeamFolders/Engineering', $folder->getMountPoint());
		$this->assertSame(1024, $folder->getQuota());

		// the path below the parent folder is taken by another folder, see MountPathResolver
		$this->assertSame('Project', $this->provider->getTeamFolder('team-2')?->getMountPoint());

		$this->assertSame('TeamFolders/Engineering', $this->provider->updateTeamFolderQuota('team-1', 2048)->getMountPoint());
		$this->assertSame(['TeamFolders/Project'], array_map(fn (TeamFolder $folder): string => $folder->getMountPoint(), $this->provider->getLinkableTeamFolders('team-1')));
	}

	public function testTeamFolderMountPointWithoutAccess(): void {
		$this->provider = $this->createProvider('TeamFolders');
		$this->userSession->method('getUser')->willReturn(null);
		$this->folderManager->expects($this->never())->method('getFoldersForUser');
		$this->folderManager->method('getFolder')->with(42)->willReturn(null);
		$this->service->method('getTeamSpaceForCircle')->willReturn(new TeamFolder(42, 'Engineering'));

		// without a user, the folder is reported with its stored mount point if it cannot be loaded
		$this->assertSame('Engineering', $this->provider->getTeamFolder('team-1')?->getMountPoint());
	}

	public function testGetSharedWithLinksToTheMountPathOfTheCurrentUser(): void {
		$this->provider = $this->createProvider('TeamFolders');
		$this->loginWithFolders([
			$this->createFolder(42, 'Engineering', true),
			$this->createFolder(43, 'Other team', true),
			$this->createFolder(44, 'Shared projects', false),
		]);
		$this->service->method('getTeamSpaceForCircle')->willReturn(new TeamFolder(42, 'Engineering'));
		$this->service->method('getGroupFoldersForCircle')->willReturn([
			new TeamFolder(42, 'Engineering'),
			new TeamFolder(43, 'Other team'),
			new TeamFolder(44, 'Shared projects'),
		]);
		$this->urlGenerator->method('linkToRouteAbsolute')
			->willReturnCallback(fn (string $route, array $parameters): string => $route . '?' . http_build_query($parameters));

		$resources = $this->provider->getSharedWith('team-1');

		$this->assertSame([
			'circles.page.indexpath?path=team%2Fteam-1',
			'files.view.index?dir=%2FTeamFolders%2FOther+team',
			'files.view.index?dir=%2FShared+projects',
		], array_map(fn ($resource): string => $resource->getUrl(), $resources));
		$this->assertSame(['Engineering', 'Other team', 'Shared projects'], array_map(fn ($resource): string => $resource->getLabel(), $resources));
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

	public function testGetLinkableTeamFoldersDelegatesToService(): void {
		$folders = [new TeamFolder(42, 'Engineering')];
		$this->service->expects($this->once())
			->method('getLinkableGroupFoldersForCircle')
			->with('team-1')
			->willReturn($folders);

		$this->assertEquals($folders, $this->provider->getLinkableTeamFolders('team-1'));
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

		$this->assertEquals($folder, $this->provider->linkTeamFolder('team-1', 42));
	}

	public function testUpdateTeamFolderQuotaDelegatesToService(): void {
		$folder = new TeamFolder(42, 'Engineering');
		$this->service->expects($this->once())
			->method('updateTeamSpaceQuota')
			->with('team-1', 1024)
			->willReturn($folder);

		$this->assertEquals($folder, $this->provider->updateTeamFolderQuota('team-1', 1024));
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
