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
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IUpdater;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\MountProviderArgs;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 */
class MountProviderTest extends TestCase {
	private FolderManager&MockObject $folderManager;
	private IUser&MockObject $user;
	private IStorage&MockObject $homeStorage;
	/** @var list<FolderDefinitionWithPermissions> */
	private array $folders = [];

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');

		// behaves like the database: all folders of the user, or the ones with the given mount points
		$this->folderManager = $this->createMock(FolderManager::class);
		$this->folderManager->method('getFoldersForUser')
			->willReturnCallback(fn (IUser $user, ?int $folderId = null, ?array $paths = null): array => array_values(array_filter(
				$this->folders,
				fn (FolderDefinitionWithPermissions $folder): bool => $paths === null || in_array($folder->mountPoint, $paths, true),
			)));

		$homeCache = $this->createMock(ICache::class);
		$homeCache->method('get')->willReturn(false);
		$this->homeStorage = $this->createMock(IStorage::class);
		$this->homeStorage->method('getCache')->willReturn($homeCache);
		$this->homeStorage->method('getUpdater')->willReturn($this->createMock(IUpdater::class));
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

		// a home storage that is not in the file cache, so there are no conflicting files
		$homeMount = $this->createMock(IMountPoint::class);
		$homeMount->method('getNumericStorageId')->willReturn(-4242);
		$homeMount->method('getStorage')->willReturn($this->homeStorage);
		$mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$mountProviderCollection->method('getHomeMountForUser')->willReturn($homeMount);

		$provider = $this->getMockBuilder(MountProvider::class)
			->setConstructorArgs([
				$this->folderManager,
				$aclManagerFactory,
				$this->createMock(IUserSession::class),
				$this->createMock(IRequest::class),
				$mountProviderCollection,
				Server::get(IDBConnection::class),
				$this->createMock(FolderStorageManager::class),
				new MountPathResolver($appConfig, $this->createMock(LoggerInterface::class)),
				false,
				false,
			])
			->onlyMethods(['getMount'])
			->getMock();
		$provider->method('getMount')
			->willReturnCallback(function (FolderDefinitionWithPermissions $folder, string $mountPoint): IMountPoint {
				$mount = $this->createMock(IMountPoint::class);
				$mount->method('getMountPoint')->willReturn($mountPoint);
				$mount->method('getMountId')->willReturn($folder->id);

				return $mount;
			});

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
			$team ? 'circle-' . $id : null,
		);
	}

	/**
	 * @param non-empty-list<string> $mountPoints
	 * @return non-empty-list<MountProviderArgs>
	 */
	private function createArgs(array $mountPoints): array {
		return array_map(function (string $mountPoint): MountProviderArgs {
			$mountInfo = $this->createMock(ICachedMountInfo::class);
			$mountInfo->method('getUser')->willReturn($this->user);
			$mountInfo->method('getMountPoint')->willReturn($mountPoint);

			return new MountProviderArgs($mountInfo, $this->createMock(ICacheEntry::class));
		}, $mountPoints);
	}

	/**
	 * @return array<string, int> mount point => folder id
	 */
	private function getMountsForUser(MountProvider $provider): array {
		$mounts = [];
		foreach ($provider->getMountsForUser($this->user, $this->createMock(IStorageFactory::class)) as $mount) {
			$this->assertArrayNotHasKey($mount->getMountPoint(), $mounts, 'Two folders are mounted at ' . $mount->getMountPoint());
			$mounts[$mount->getMountPoint()] = (int)$mount->getMountId();
		}

		return $mounts;
	}

	/**
	 * @return array<string, int> mount point => folder id
	 */
	private function getMountsForPath(MountProvider $provider, string $mountPoint, string ...$moreMountPoints): array {
		return array_map(
			fn (IMountPoint $mount): int => (int)$mount->getMountId(),
			$provider->getMountsForPath($mountPoint, false, $this->createArgs([$mountPoint, ...array_values($moreMountPoints)]), $this->createMock(IStorageFactory::class)),
		);
	}

	/**
	 * @return array<string, array{?string, ?string}>
	 */
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
		$this->folders = [$this->createFolder(1, '/', true)];
		$provider = $this->createProvider($prefix, $scope);

		$this->assertSame(['/alice/files//' => 1], $this->getMountsForPath($provider, '/alice/files/'));
	}

	/**
	 * @dataProvider prefixProvider
	 */
	public function testGetMountsForPathRootMountWithOtherFolders(?string $prefix, ?string $scope): void {
		$this->folders = [
			$this->createFolder(1, '/', false),
			$this->createFolder(2, 'Project', true),
			$this->createFolder(3, 'Marketing', false),
		];
		$provider = $this->createProvider($prefix, $scope);
		$teamPath = $prefix === null ? 'Project' : $prefix . '/Project';

		$expected = ['/alice/files//' => 1, '/alice/files/' . $teamPath => 2];
		if ($scope !== MountPathResolver::SCOPE_ALL) {
			$expected['/alice/files/Marketing'] = 3;
		}

		$this->assertSame($expected, $this->getMountsForPath($provider, '/alice/files/', '/alice/files/' . $teamPath . '/', '/alice/files/Marketing/'));
	}

	/**
	 * @return array<string, array{list<array{int, string, bool}>, ?string, ?string}>
	 */
	public static function setupProvider(): array {
		$scenarios = [
			'regular folders' => [[[1, 'Marketing', false], [2, 'Project', true], [3, 'a/b', false], [4, 'a', true]]],
			'root mount' => [[[1, '/', false], [2, 'Project', true], [3, 'Marketing', false]]],
			'team folder collides with an admin folder' => [[[1, 'Project', true], [2, 'TeamFolders/Project', false]]],
			'admin folder is the parent folder' => [[[1, 'TeamFolders', false], [2, 'Project', true], [3, 'Other', true]]],
			'admin folder is nested in the parent folder' => [[[1, 'TeamFolders/Docs', false], [2, 'Docs', true], [3, 'Docs/More', false]]],
			'fallback is taken as well' => [[[1, 'Project', false], [2, 'TeamFolders/Project', false], [3, 'Project', true]]],
			'duplicate mount points' => [[[1, 'Shared', false], [2, 'Shared', false], [3, 'X', true], [4, 'X', true]]],
		];

		$cases = [];
		foreach ($scenarios as $name => [$folders]) {
			foreach (self::prefixProvider() as $prefixName => [$prefix, $scope]) {
				$cases[$name . ', ' . $prefixName] = [$folders, $prefix, $scope];
			}
		}

		return $cases;
	}

	/**
	 * The full setup must never mount two folders at the same path and the partial setup
	 * must return exactly the folder the full setup mounts at the requested path.
	 *
	 * @dataProvider setupProvider
	 * @param list<array{int, string, bool}> $folders
	 */
	public function testFullAndPartialSetupAgree(array $folders, ?string $prefix, ?string $scope): void {
		$this->folders = array_map(fn (array $folder): FolderDefinitionWithPermissions => $this->createFolder(...$folder), $folders);
		$provider = $this->createProvider($prefix, $scope);

		$fullSetup = $this->getMountsForUser($provider);

		foreach ($fullSetup as $mountPoint => $folderId) {
			$this->assertSame([$mountPoint => $folderId], $this->getMountsForPath($provider, rtrim($mountPoint, '/') . '/'), 'partial setup of ' . $mountPoint);
		}

		$this->assertSame($fullSetup, $this->getMountsForPath($provider, ...array_map(fn (string $mountPoint): string => rtrim($mountPoint, '/') . '/', array_keys($fullSetup))));
	}

	public function testCollisionKeepsStoredMountPoint(): void {
		$this->folders = [
			$this->createFolder(1, 'Project', true),
			$this->createFolder(2, 'TeamFolders/Project', false),
			$this->createFolder(3, 'Other', true),
		];

		$this->assertSame([
			'/alice/files/Project' => 1,
			'/alice/files/TeamFolders/Project' => 2,
			'/alice/files/TeamFolders/Other' => 3,
		], $this->getMountsForUser($this->createProvider('TeamFolders', null)));
		$this->assertSame([], $this->getMountsForPath($this->createProvider('TeamFolders', null), '/alice/files/TeamFolders/TeamFolders/Project/'));
	}

	public function testDuplicateMountPointIsNotOverwritten(): void {
		$this->folders = [
			$this->createFolder(2, 'Shared', false),
			$this->createFolder(1, 'Shared', false),
		];
		$provider = $this->createProvider(null, null);

		$this->assertSame(['/alice/files/Shared' => 1], $this->getMountsForUser($provider));
		$this->assertSame(['/alice/files/Shared' => 1], $this->getMountsForPath($provider, '/alice/files/Shared/'));
	}

	public function testParentFolderIsCreatedInHome(): void {
		$this->folders = [$this->createFolder(1, 'Project', true)];

		$this->homeStorage->expects($this->exactly(2))
			->method('mkdir')
			->willReturnCallback(function (string $path): bool {
				$this->assertContains($path, ['files/Shared', 'files/Shared/Teams']);
				return true;
			});

		$this->assertSame(['/alice/files/Shared/Teams/Project' => 1], $this->getMountsForUser($this->createProvider('Shared/Teams', null)));
	}

	public function testParentFolderIsNotCreatedInsideGroupFolder(): void {
		$this->folders = [
			$this->createFolder(1, 'Shared', false),
			$this->createFolder(2, 'Project', true),
		];

		// "Shared" is a group folder, so "Shared/Teams" would have to be created inside it
		$this->homeStorage->expects($this->never())->method('mkdir');

		$this->assertSame([
			'/alice/files/Shared' => 1,
			'/alice/files/Shared/Teams/Project' => 2,
		], $this->getMountsForUser($this->createProvider('Shared/Teams', null)));
	}

	public function testParentFolderIsNotCreatedWithRootMount(): void {
		$this->folders = [
			$this->createFolder(1, '/', false),
			$this->createFolder(2, 'Project', true),
		];

		$this->homeStorage->expects($this->never())->method('mkdir');

		$this->assertSame([
			'/alice/files//' => 1,
			'/alice/files/TeamFolders/Project' => 2,
		], $this->getMountsForUser($this->createProvider('TeamFolders', null)));
	}
}
