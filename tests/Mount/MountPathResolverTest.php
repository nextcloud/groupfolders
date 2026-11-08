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
use Test\TestCase;

class MountPathResolverTest extends TestCase {
	private function createResolver(?string $prefix, ?string $scope = null): MountPathResolver {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default): string => match ($key) {
				MountPathResolver::CONFIG_KEY => $prefix ?? $default,
				MountPathResolver::SCOPE_CONFIG_KEY => $scope ?? $default,
				default => $default,
			});

		return new MountPathResolver($config);
	}

	private function createFolder(string $mountPoint, bool $team): FolderDefinition {
		return new FolderDefinition(1, $mountPoint, -3, false, false, 1, 1, [], $team ? 'circle-id' : null);
	}

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
		$this->assertSame('Marketing', $resolver->getUserMountPath($this->createFolder('Marketing', true)));
		$this->assertSame('a/b', $resolver->getUserMountPath($this->createFolder('a/b', false)));
		$this->assertSame(['a/b'], $resolver->getMountPointCandidates('/a/b/'));
	}

	public function testTeamScopeOnlyPrefixesTeamFolders(): void {
		$resolver = $this->createResolver('Shared/Teams');

		$this->assertSame('Shared/Teams/Project', $resolver->getUserMountPath($this->createFolder('Project', true)));
		$this->assertSame('Marketing', $resolver->getUserMountPath($this->createFolder('Marketing', false)));
	}

	public function testAllScopePrefixesEveryFolder(): void {
		$resolver = $this->createResolver('TeamFolders', MountPathResolver::SCOPE_ALL);

		$this->assertSame('TeamFolders/Project', $resolver->getUserMountPath($this->createFolder('Project', true)));
		$this->assertSame('TeamFolders/a/b', $resolver->getUserMountPath($this->createFolder('a/b', false)));
	}

	public function testUnknownScopeFallsBackToTeams(): void {
		$resolver = $this->createResolver('TeamFolders', 'something');

		$this->assertSame(MountPathResolver::SCOPE_TEAMS, $resolver->getScope());
		$this->assertSame('Marketing', $resolver->getUserMountPath($this->createFolder('Marketing', false)));
	}

	public function testMountPointCandidates(): void {
		$resolver = $this->createResolver('TeamFolders');

		$this->assertSame(['TeamFolders/Project', 'Project'], $resolver->getMountPointCandidates('/TeamFolders/Project/'));
		$this->assertSame(['Marketing'], $resolver->getMountPointCandidates('Marketing'));
		$this->assertSame(['TeamFoldersOld/Marketing'], $resolver->getMountPointCandidates('TeamFoldersOld/Marketing'));
		$this->assertSame(['/'], $resolver->getMountPointCandidates(''));
		$this->assertSame(['/'], $resolver->getMountPointCandidates('/'));
	}

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
	public function testRootMountIsNeverPrefixed(?string $prefix, ?string $scope): void {
		$resolver = $this->createResolver($prefix, $scope);

		foreach ([true, false] as $team) {
			$folder = $this->createFolder('/', $team);
			$this->assertFalse($resolver->appliesTo($folder));
			$this->assertSame('/', $resolver->getUserMountPath($folder));
		}
	}
}
