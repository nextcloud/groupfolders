<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Team;

use OCA\Circles\CirclesManager;
use OCA\GroupFolders\Folder\FolderDefinitionWithMappings;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Team\GroupFoldersTeamResourceProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Teams\TeamResource;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class GroupFoldersTeamResourceProviderTest extends TestCase {
	private FolderManager&MockObject $folderManager;
	private IL10N&MockObject $l10n;
	private IURLGenerator&MockObject $urlGenerator;
	private GroupFoldersTeamResourceProvider $provider;

	protected function setUp(): void {
		parent::setUp();

		$this->folderManager = $this->createMock(FolderManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->l10n->method('t')->willReturnArgument(0);
		$this->urlGenerator->method('linkToRouteAbsolute')
			->willReturnCallback(function (string $route, array $args = []): string {
				$dir = $args['dir'] ?? '';
				return 'https://cloud.example/' . $route . '?dir=' . (is_string($dir) ? $dir : '');
			});

		$this->provider = new GroupFoldersTeamResourceProvider(
			$this->l10n,
			$this->urlGenerator,
			$this->folderManager,
		);
	}

	/**
	 * @param array<string, array{displayName: string, permissions: int, type: 'group'|'circle'}> $groups
	 */
	private function folder(int $id, string $mountPoint, array $groups): FolderDefinitionWithMappings {
		return new FolderDefinitionWithMappings($id, $mountPoint, 0, false, false, 1, 1, [], $groups, []);
	}

	/**
	 * @return array{displayName: string, permissions: int, type: 'circle'}
	 */
	private function circleApplicable(string $displayName = 'Team'): array {
		return ['displayName' => $displayName, 'permissions' => 31, 'type' => 'circle'];
	}

	/**
	 * @return array{displayName: string, permissions: int, type: 'group'}
	 */
	private function groupApplicable(string $displayName = 'Group'): array {
		return ['displayName' => $displayName, 'permissions' => 31, 'type' => 'group'];
	}

	private function withCircles(): void {
		$this->folderManager->method('getCirclesManager')->willReturn($this->createMock(CirclesManager::class));
	}

	public function testStaticIdentity(): void {
		$this->assertSame('groupfolders', $this->provider->getId());
		$this->assertSame('Team folders', $this->provider->getName());
		$this->assertStringContainsString('<svg', $this->provider->getIconSvg());
	}

	public function testGetSharedWithReturnsOnlyFoldersMappedToTheTeam(): void {
		$this->withCircles();
		$this->folderManager->method('getAllFolders')->willReturn([
			1 => $this->folder(1, 'Design', ['team-1' => $this->circleApplicable('Design Team')]),
			2 => $this->folder(2, 'Marketing', ['group-x' => $this->groupApplicable()]),
			3 => $this->folder(3, 'Other', ['team-2' => $this->circleApplicable()]),
		]);

		$resources = $this->provider->getSharedWith('team-1');

		$this->assertCount(1, $resources);
		$resource = $resources[0];
		$this->assertInstanceOf(TeamResource::class, $resource);
		$this->assertSame('1', $resource->getId());
		$this->assertSame('Design', $resource->getLabel());
		$this->assertStringContainsString('dir=/Design', $resource->getUrl());
		$this->assertStringContainsString('<svg', (string)$resource->getIconSvg());
	}

	public function testGetSharedWithReturnsEmptyWhenCirclesDisabled(): void {
		$this->folderManager->method('getCirclesManager')->willReturn(null);
		$this->folderManager->expects($this->never())->method('getAllFolders');

		$this->assertSame([], $this->provider->getSharedWith('team-1'));
	}

	public function testIsSharedWithTeam(): void {
		$this->withCircles();
		$this->folderManager->method('getAllFolders')->willReturn([
			5 => $this->folder(5, 'Design', ['team-1' => $this->circleApplicable()]),
		]);

		$this->assertTrue($this->provider->isSharedWithTeam('team-1', '5'));
		$this->assertFalse($this->provider->isSharedWithTeam('team-2', '5'));
		$this->assertFalse($this->provider->isSharedWithTeam('team-1', '99'));
	}

	public function testIsSharedWithTeamReturnsFalseWhenCirclesDisabled(): void {
		$this->folderManager->method('getCirclesManager')->willReturn(null);

		$this->assertFalse($this->provider->isSharedWithTeam('team-1', '5'));
	}

	public function testGetTeamsForResourceReturnsCircleIdsOnly(): void {
		$this->withCircles();
		$this->folderManager->method('getAllFolders')->willReturn([
			7 => $this->folder(7, 'Shared', [
				'team-1' => $this->circleApplicable(),
				'group-x' => $this->groupApplicable(),
				'team-2' => $this->circleApplicable(),
			]),
		]);

		$this->assertEqualsCanonicalizing(['team-1', 'team-2'], $this->provider->getTeamsForResource('7'));
	}

	public function testGetTeamsForResourceReturnsEmptyForUnknownFolder(): void {
		$this->withCircles();
		$this->folderManager->method('getAllFolders')->willReturn([]);

		$this->assertSame([], $this->provider->getTeamsForResource('123'));
	}
}
