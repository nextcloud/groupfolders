<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\Mount;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Mount\MountPathResolver;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class MountPathResolverTest extends TestCase {
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function createResolver(?string $prefix, ?string $scope = null): MountPathResolver {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default): string => match ($key) {
				MountPathResolver::CONFIG_KEY => $prefix ?? $default,
				MountPathResolver::SCOPE_CONFIG_KEY => $scope ?? $default,
				default => $default,
			});

		return new MountPathResolver($config, $this->logger);
	}

	private function createFolder(string $mountPoint, bool $team, int $id = 1): FolderDefinition {
		return new FolderDefinition($id, $mountPoint, -3, false, false, 1, $id, [], $team ? 'circle-' . $id : null);
	}

	/**
	 * @return list<array{string, string}>
	 */
	public static function sanitizeProvider(): array {
		return [
			['', ''],
			['TeamFolders', 'TeamFolders'],
			['/TeamFolders/', 'TeamFolders'],
			['  TeamFolders  ', 'TeamFolders'],
			['Shared//Teams', 'Shared/Teams'],
			['Shared\\Teams', 'Shared/Teams'],
			['../Teams', 'Teams'],
			['./a/../b', 'a/b'],
			['/', ''],
		];
	}

	/**
	 * @dataProvider sanitizeProvider
	 */
	public function testSanitize(string $input, string $expected): void {
		$this->assertSame($expected, MountPathResolver::sanitize($input));
		$this->assertSame($expected, $this->createResolver($input)->getPrefix());
	}

	public function testDisabledByDefault(): void {
		$resolver = $this->createResolver(null);

		$this->assertSame('', $resolver->getPrefix());
		$this->assertSame(MountPathResolver::SCOPE_TEAMS, $resolver->getScope());
		$this->assertFalse($resolver->appliesTo($this->createFolder('Marketing', true)));
		$this->assertSame('Marketing', $resolver->getPreferredMountPath($this->createFolder('Marketing', true)));
		$this->assertSame('a/b', $resolver->getPreferredMountPath($this->createFolder('a/b', false)));
		$this->assertSame(['a/b'], $resolver->getMountPointCandidates('/a/b/'));
	}

	public function testTeamScopeOnlyMovesTeamFolders(): void {
		$resolver = $this->createResolver('Shared/Teams');

		$this->assertSame('Shared/Teams/Project', $resolver->getPreferredMountPath($this->createFolder('Project', true)));
		$this->assertSame('Marketing', $resolver->getPreferredMountPath($this->createFolder('Marketing', false)));
	}

	public function testAllScopeMovesEveryFolder(): void {
		$resolver = $this->createResolver('TeamFolders', MountPathResolver::SCOPE_ALL);

		$this->assertSame('TeamFolders/Project', $resolver->getPreferredMountPath($this->createFolder('Project', true)));
		$this->assertSame('TeamFolders/a/b', $resolver->getPreferredMountPath($this->createFolder('a/b', false)));
	}

	public function testUnknownScopeFallsBackToTeams(): void {
		$resolver = $this->createResolver('TeamFolders', 'something');

		$this->assertSame(MountPathResolver::SCOPE_TEAMS, $resolver->getScope());
		$this->assertSame('Marketing', $resolver->getPreferredMountPath($this->createFolder('Marketing', false)));
	}

	public function testMountPointCandidates(): void {
		$resolver = $this->createResolver('TeamFolders');

		$this->assertSame(['TeamFolders/Project', 'Project', 'TeamFolders/TeamFolders/Project'], $resolver->getMountPointCandidates('/TeamFolders/Project/'));
		$this->assertSame(['Marketing', 'TeamFolders/Marketing'], $resolver->getMountPointCandidates('Marketing'));
		$this->assertSame(['TeamFoldersOld/Marketing', 'TeamFolders/TeamFoldersOld/Marketing'], $resolver->getMountPointCandidates('TeamFoldersOld/Marketing'));
		$this->assertSame(['/'], $resolver->getMountPointCandidates(''));
		$this->assertSame(['/'], $resolver->getMountPointCandidates('/'));
	}

	public function testCollisionCandidates(): void {
		$resolver = $this->createResolver('TeamFolders');

		// a new team folder "Project" is mounted at "TeamFolders/Project"
		$this->assertSame(['TeamFolders/Project', 'Project', 'TeamFolders/TeamFolders/Project'], $resolver->getCollisionCandidates('Project', true));
		// a new admin folder "TeamFolders/Project" can collide with the team folder "Project"
		$this->assertSame(['TeamFolders/Project', 'Project', 'TeamFolders/TeamFolders/Project'], $resolver->getCollisionCandidates('TeamFolders/Project', false));
		$this->assertSame(['Project'], $this->createResolver(null)->getCollisionCandidates('Project', true));
	}

	/**
	 * @return array<string, array{?string, ?string}>
	 */
	public static function rootMountProvider(): array {
		return [
			'no prefix' => [null, null],
			'team scope' => ['TeamFolders', MountPathResolver::SCOPE_TEAMS],
			'all scope' => ['TeamFolders', MountPathResolver::SCOPE_ALL],
		];
	}

	/**
	 * @dataProvider rootMountProvider
	 */
	public function testRootMountIsNeverMoved(?string $prefix, ?string $scope): void {
		$resolver = $this->createResolver($prefix, $scope);

		foreach ([true, false] as $team) {
			$folder = $this->createFolder('/', $team);
			$this->assertFalse($resolver->appliesTo($folder));
			$this->assertSame('/', $resolver->getPreferredMountPath($folder));
			$this->assertSame([1 => '/'], $resolver->resolveMountPaths([$folder]));
		}
	}

	public function testResolveWithoutCollisions(): void {
		$resolver = $this->createResolver('TeamFolders');

		$this->assertSame([
			1 => 'Marketing',
			2 => 'TeamFolders/Project',
			3 => 'a/b',
		], $resolver->resolveMountPaths([
			$this->createFolder('Marketing', false, 1),
			$this->createFolder('Project', true, 2),
			$this->createFolder('a/b', false, 3),
		]));
	}

	public function testStoredMountPointWinsOverParentFolder(): void {
		$resolver = $this->createResolver('TeamFolders');

		$this->logger->expects($this->once())
			->method('info')
			->with($this->stringContains('instead of'), $this->callback(fn (array $context): bool => $context['folderId'] === 1 && $context['path'] === 'TeamFolders/Project'));
		$this->logger->expects($this->never())->method('warning');

		// the team folder has the lower id, the admin folder still keeps its path
		$this->assertSame([
			1 => 'Project',
			2 => 'TeamFolders/Project',
		], $resolver->resolveMountPaths([
			$this->createFolder('Project', true, 1),
			$this->createFolder('TeamFolders/Project', false, 2),
		]));
	}

	public function testNoCollisionWithAllScope(): void {
		$resolver = $this->createResolver('TeamFolders', MountPathResolver::SCOPE_ALL);

		$this->assertSame([
			1 => 'TeamFolders/Project',
			2 => 'TeamFolders/TeamFolders/Project',
		], $resolver->resolveMountPaths([
			$this->createFolder('Project', true, 1),
			$this->createFolder('TeamFolders/Project', false, 2),
		]));
	}

	public function testFolderWithoutFreePathIsNotMounted(): void {
		$resolver = $this->createResolver('TeamFolders');

		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('is not mounted'), $this->callback(fn (array $context): bool => $context['folderId'] === 3 && $context['otherId'] === 1));

		// "TeamFolders/Project" is taken by folder 2, the fallback "Project" by folder 1
		$this->assertSame([
			1 => 'Project',
			2 => 'TeamFolders/Project',
		], $resolver->resolveMountPaths([
			$this->createFolder('Project', false, 1),
			$this->createFolder('TeamFolders/Project', false, 2),
			$this->createFolder('Project', true, 3),
		]));
	}

	public function testDuplicateMountPointsKeepLowestId(): void {
		$resolver = $this->createResolver(null);

		$this->logger->expects($this->once())->method('warning');

		$this->assertSame([4 => 'Shared'], $resolver->resolveMountPaths([
			$this->createFolder('Shared', false, 7),
			$this->createFolder('Shared', false, 4),
		]));
	}

	public function testResolveDoesNotDependOnOrder(): void {
		$resolver = $this->createResolver('TeamFolders');

		$folders = [
			$this->createFolder('Project', true, 1),
			$this->createFolder('TeamFolders/Project', false, 2),
			$this->createFolder('Other', true, 3),
			$this->createFolder('Project', true, 4),
		];
		$expected = $resolver->resolveMountPaths($folders);
		ksort($expected);

		$reversed = $resolver->resolveMountPaths(array_reverse($folders));
		ksort($reversed);

		$this->assertSame($expected, $reversed);
		$this->assertSame([1 => 'Project', 2 => 'TeamFolders/Project', 3 => 'TeamFolders/Other'], $expected);
	}
}
