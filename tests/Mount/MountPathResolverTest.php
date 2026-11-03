<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\Mount;

use OCA\GroupFolders\Mount\MountPathResolver;
use OCP\IAppConfig;
use Test\TestCase;

class MountPathResolverTest extends TestCase {
	private function createResolver(string $configured): MountPathResolver {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')
			->with('groupfolders', MountPathResolver::CONFIG_KEY, MountPathResolver::DEFAULT_PREFIX)
			->willReturn($configured);

		return new MountPathResolver($config);
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
		$resolver = $this->createResolver(MountPathResolver::DEFAULT_PREFIX);

		$this->assertSame('', $resolver->getPrefix());
		$this->assertSame('Marketing', $resolver->getUserMountPath('Marketing'));
		$this->assertSame('a/b', $resolver->getUserMountPath('a/b'));
		$this->assertSame('Marketing', $resolver->getMountPointFromUserPath('Marketing'));
		$this->assertSame('a/b', $resolver->getMountPointFromUserPath('/a/b/'));
	}

	public function testWithPrefix(): void {
		$resolver = $this->createResolver('Shared/Teams');

		$this->assertSame('Shared/Teams/Marketing', $resolver->getUserMountPath('Marketing'));
		$this->assertSame('Shared/Teams/a/b', $resolver->getUserMountPath('a/b'));
		$this->assertSame('Marketing', $resolver->getMountPointFromUserPath('Shared/Teams/Marketing'));
		$this->assertSame('a/b', $resolver->getMountPointFromUserPath('/Shared/Teams/a/b/'));
	}

	public function testPathOutsidePrefix(): void {
		$resolver = $this->createResolver('TeamFolders');

		$this->assertNull($resolver->getMountPointFromUserPath('Marketing'));
		$this->assertNull($resolver->getMountPointFromUserPath('TeamFolders'));
		$this->assertNull($resolver->getMountPointFromUserPath('TeamFoldersOld/Marketing'));
	}
}
