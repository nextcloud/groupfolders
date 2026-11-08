<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\Mount;

use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\MountPathResolver;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\MountProviderArgs;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorageFactory;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MountProviderTest extends TestCase {
	private FolderManager&MockObject $folderManager;
	private IUser&MockObject $user;

	protected function setUp(): void {
		parent::setUp();

		$this->folderManager = $this->createMock(FolderManager::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
	}

	private function createProvider(?string $prefix, ?string $scope): MountProvider&MockObject {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')
			->willReturnCallback(fn (string $app, string $key, string $default): string => match ($key) {
				MountPathResolver::CONFIG_KEY => $prefix ?? $default,
				MountPathResolver::SCOPE_CONFIG_KEY => $scope ?? $default,
				default => $default,
			});

		$aclManagerFactory = $this->createMock(ACLManagerFactory::class);
		$aclManagerFactory->method('getACLManager')->willReturn($this->createMock(ACLManager::class));

		$provider = $this->getMockBuilder(MountProvider::class)
			->setConstructorArgs([
				$this->folderManager,
				$aclManagerFactory,
				$this->createMock(IUserSession::class),
				$this->createMock(IRequest::class),
				$this->createMock(IMountProviderCollection::class),
				$this->createMock(IDBConnection::class),
				$this->createMock(FolderStorageManager::class),
				new MountPathResolver($appConfig),
				false,
				false,
			])
			->onlyMethods(['getMount'])
			->getMock();
		$provider->method('getMount')
			->willReturnCallback(fn (FolderDefinitionWithPermissions $folder, string $mountPoint): IMountPoint => $this->createMock(IMountPoint::class));

		return $provider;
	}

	private function createFolder(int $id, string $mountPoint, bool $team): FolderDefinitionWithPermissions {
		return new FolderDefinitionWithPermissions(
			$id,
			$mountPoint,
			-3,
			false,
			false,
			1,
			$id,
			[],
			$this->createMock(ICacheEntry::class),
			Constants::PERMISSION_ALL,
			$team ? 'circle-id' : null,
		);
	}

	/**
	 * @return list<MountProviderArgs>
	 */
	private function createArgs(string ...$mountPoints): array {
		return array_map(function (string $mountPoint): MountProviderArgs {
			$mountInfo = $this->createMock(ICachedMountInfo::class);
			$mountInfo->method('getUser')->willReturn($this->user);
			$mountInfo->method('getMountPoint')->willReturn($mountPoint);

			return new MountProviderArgs($mountInfo, $this->createMock(ICacheEntry::class));
		}, $mountPoints);
	}

	public static function prefixProvider(): array {
		return [
			'no prefix' => [null, null],
			'prefix, team scope' => ['TeamFolders', MountPathResolver::SCOPE_TEAMS],
			'prefix, all scope' => ['TeamFolders', MountPathResolver::SCOPE_ALL],
		];
	}

	/**
	 * @dataProvider prefixProvider
	 */
	public function testGetMountsForPathRootMount(?string $prefix, ?string $scope): void {
		$provider = $this->createProvider($prefix, $scope);

		$this->folderManager->expects($this->once())
			->method('getFoldersForUser')
			->with($this->user, null, ['/'])
			->willReturn([$this->createFolder(1, '/', true)]);

		$mounts = $provider->getMountsForPath('/alice/files/', false, $this->createArgs('/alice/files/'), $this->createMock(IStorageFactory::class));

		$this->assertSame(['/alice/files//'], array_keys($mounts));
	}

	/**
	 * @dataProvider prefixProvider
	 */
	public function testGetMountsForPathRootMountWithOtherFolders(?string $prefix, ?string $scope): void {
		$provider = $this->createProvider($prefix, $scope);
		$teamPath = $prefix === null ? 'Project' : $prefix . '/Project';

		$this->folderManager->expects($this->once())
			->method('getFoldersForUser')
			->willReturnCallback(function (IUser $user, ?int $folderId, array $paths): array {
				$this->assertContains('/', $paths);
				$this->assertContains('Project', $paths);
				$this->assertContains('Marketing', $paths);

				return [
					$this->createFolder(1, '/', false),
					$this->createFolder(2, 'Project', true),
					$this->createFolder(3, 'Marketing', false),
				];
			});

		$mounts = $provider->getMountsForPath(
			'/alice/files/',
			true,
			$this->createArgs('/alice/files/', '/alice/files/' . $teamPath . '/', '/alice/files/Marketing/'),
			$this->createMock(IStorageFactory::class),
		);

		$expected = ['/alice/files//', '/alice/files/' . $teamPath];
		if ($scope !== MountPathResolver::SCOPE_ALL) {
			$expected[] = '/alice/files/Marketing';
		}

		$this->assertSame($expected, array_keys($mounts));
	}
}
