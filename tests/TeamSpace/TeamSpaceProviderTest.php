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
use OCP\Teams\TeamFolder;
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
			->method('getGroupFoldersForCircle')
			->with('team-1')
			->willReturn([
				new TeamFolder(42, 'Engineering'),
				new TeamFolder(43, 'Shared projects'),
			]);
		$this->urlGenerator->expects($this->exactly(2))
			->method('getAbsoluteURL')
			->willReturnCallback(static fn (string $url): string => 'https://cloud.example' . $url);

		$resources = $this->provider->getSharedWith('team-1');

		$this->assertCount(2, $resources);
		$this->assertSame('42', $resources[0]->getId());
		$this->assertSame('Engineering', $resources[0]->getLabel());
		$this->assertSame('https://cloud.example/apps/files/?dir=/Engineering', $resources[0]->getUrl());
		$this->assertSame('43', $resources[1]->getId());
		$this->assertSame('Shared projects', $resources[1]->getLabel());
		$this->assertSame('https://cloud.example/apps/files/?dir=/Shared%20projects', $resources[1]->getUrl());
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
