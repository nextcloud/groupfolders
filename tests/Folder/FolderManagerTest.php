<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Folder;

use OC\Files\Cache\CacheEntry;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\ResponseDefinitions;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeLoader;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * @group DB
 *
 * @phpstan-import-type GroupFoldersApplicable from ResponseDefinitions
 */
class FolderManagerTest extends TestCase {
	private FolderManager $manager;
	private IGroupManager&MockObject $groupManager;
	private IMimeTypeLoader&MockObject $mimeLoader;
	private LoggerInterface&MockObject $logger;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IConfig&MockObject $config;
	private IUserMappingManager&MockObject $userMappingManager;
	private FolderStorageManager $folderStorageManager;
	private IAppConfig $appConfig;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->mimeLoader = $this->createMock(IMimeTypeLoader::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userMappingManager = $this->createMock(IUserMappingManager::class);
		$this->folderStorageManager = Server::get(FolderStorageManager::class);
		$this->appConfig = Server::get(IAppConfig::class);

		$this->manager = new FolderManager(
			Server::get(IDBConnection::class),
			$this->groupManager,
			$this->mimeLoader,
			$this->logger,
			$this->eventDispatcher,
			$this->config,
			$this->userMappingManager,
			$this->folderStorageManager,
			$this->appConfig,
		);
		$this->clean();
	}

	private function clean(): void {
		$db = Server::get(IDBConnection::class);

		$db->getQueryBuilder()->delete('group_folders')->executeStatement();
		$db->getQueryBuilder()->delete('group_folders_groups')->executeStatement();

		// Remove any filecache rows seeded by seedFilecacheForFolder().
		// storage = 0 is used as a sentinel as no real storage is
		// ever assigned numeric ID 0, so this delete is always safe.
		$q = $db->getQueryBuilder();
		$q->delete('filecache')
			->where($q->expr()->eq('storage', $q->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * Insert a minimal filecache row so that getFolder()'s INNER JOIN on
	 * filecache succeeds, then patch group_folders.root_id to point at it.
	 * storage = 0 as a sentinel value that clean() sweeps unconditionally.
	 */
	private function seedFilecacheForFolder(int $folderId): void {
		$db = Server::get(IDBConnection::class);
		$now = time();

		$insert = $db->getQueryBuilder();
		$insert->insert('filecache')
			->values([
				'storage' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'path' => $insert->createNamedParameter(''),
				'path_hash' => $insert->createNamedParameter(md5('')),
				'parent' => $insert->createNamedParameter(-1, IQueryBuilder::PARAM_INT),
				'name' => $insert->createNamedParameter(''),
				'mimetype' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'mimepart' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'size' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'mtime' => $insert->createNamedParameter($now, IQueryBuilder::PARAM_INT),
				'storage_mtime' => $insert->createNamedParameter($now, IQueryBuilder::PARAM_INT),
				'etag' => $insert->createNamedParameter(uniqid()),
				'encrypted' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'permissions' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			]);
		$insert->executeStatement();
		$filecacheId = $insert->getLastInsertId();

		$update = $db->getQueryBuilder();
		$update->update('group_folders')
			->set('root_id', $update->createNamedParameter($filecacheId, IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq(
				'folder_id',
				$update->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)
			))
			->executeStatement();
	}

	/**
	 * @param list<array{mount_point: string, groups: array<string, GroupFoldersApplicable>, acl?: bool, quota?: int, size?: int, root_id?: int, storage_id?: int}> $folders
	 */
	private function assertHasFolders(array $folders): void {
		$existingFolders = array_values($this->manager->getAllFolders());
		usort($existingFolders, fn (FolderDefinition $a, FolderDefinition $b): int => strcmp($a->mountPoint, $b->mountPoint));
		usort($folders, fn (array $a, array $b): int => strcmp($a['mount_point'], $b['mount_point']));

		foreach ($folders as &$folder) {
			if (!isset($folder['quota'])) {
				$folder['quota'] = FileInfo::SPACE_UNLIMITED;
			}

			if (!isset($folder['acl'])) {
				$folder['acl'] = false;
			}
		}

		$existingFolders = array_map(fn (FolderDefinition $existingFolder): array => [
			'mount_point' => $existingFolder->mountPoint,
			'quota' => $existingFolder->quota,
			'acl' => $existingFolder->acl,
			'groups' => $existingFolder->groups,
		], $existingFolders);

		$this->assertEquals($folders, $existingFolders);
	}

	public function testCreateFolder(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$this->manager->createFolder('foo');

		$this->assertHasFolders([
			['mount_point' => 'foo', 'groups' => []]
		]);
	}

	public function testSetMountpoint(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->createFolder('bar');

		$this->manager->renameFolder($folderId1, 'foo2');

		$this->assertHasFolders([
			['mount_point' => 'foo2', 'groups' => []],
			['mount_point' => 'bar', 'groups' => []]
		]);
	}

	public function testAddApplicable(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$folderId2 = $this->manager->createFolder('bar');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->addApplicableGroup($folderId2, 'g1');
		$this->manager->addApplicableGroup($folderId2, 'g3');

		$this->assertHasFolders(
			[
				[
					'mount_point' => 'foo',
					'groups'
						=> [
							'g1' => [
								'displayName' => 'g1',
								'permissions' => Constants::PERMISSION_ALL, 'type' => 'group'
							],
							'g2' => [
								'displayName' => 'g2',
								'permissions' => Constants::PERMISSION_ALL, 'type' => 'group'
							]
						]
				],
				[
					'mount_point' => 'bar',
					'groups'
						=> [
							'g1' => [

								'displayName' => 'g1',
								'permissions' => Constants::PERMISSION_ALL,
								'type' => 'group'
							],
							'g3' => [
								'displayName' => 'g3',
								'permissions' => Constants::PERMISSION_ALL,
								'type' => 'group'
							]
						]
				]
			]
		);
	}

	public function testSetPermissions(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->setGroupPermissions($folderId1, 'g1', 2);

		$this->assertHasFolders(
			[
				[
					'mount_point' => 'foo',
					'groups'
						=> [
							'g1' => [
								'displayName' => 'g1',
								'permissions' => 2,
								'type' => 'group'
							],
							'g2' => [
								'displayName' => 'g2',
								'permissions' => Constants::PERMISSION_ALL,
								'type' => 'group'
							]
						]
				]
			]
		);
	}

	public function testRemoveApplicable(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$folderId2 = $this->manager->createFolder('bar');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->addApplicableGroup($folderId2, 'g1');
		$this->manager->addApplicableGroup($folderId2, 'g3');

		$this->manager->removeApplicableGroup($folderId1, 'g1');

		$this->assertHasFolders(
			[
				[
					'mount_point' => 'foo',
					'groups'
						=> [
							'g2' => [
								'displayName' => 'g2',
								'permissions' => Constants::PERMISSION_ALL,
								'type' => 'group'
							]
						]
				],
				[
					'mount_point' => 'bar',
					'groups'
						=> [
							'g1' => [
								'displayName' => 'g1',
								'permissions' => Constants::PERMISSION_ALL,
								'type' => 'group'
							],
							'g3' => [
								'displayName' => 'g3',
								'permissions' => Constants::PERMISSION_ALL,
								'type' => 'group'
							]
						]
				]
			]
		);
	}

	public function testRemoveFolder(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->createFolder('bar');

		$this->manager->removeFolder($folderId1);

		$this->assertHasFolders([
			['mount_point' => 'bar', 'groups' => []]
		]);
	}

	public function testRenameFolder(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->createFolder('other');

		$this->manager->renameFolder($folderId1, 'bar');

		$this->assertHasFolders([
			['mount_point' => 'bar', 'groups' => []],
			['mount_point' => 'other', 'groups' => []],
		]);
	}

	public function testSetACL(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->createFolder('other');

		$this->manager->setFolderACL($folderId1, true);

		$this->assertHasFolders([
			['mount_point' => 'foo', 'groups' => [], 'acl' => true],
			['mount_point' => 'other', 'groups' => []],
		]);

		$this->manager->setFolderACL($folderId1, false);

		$this->assertHasFolders([
			['mount_point' => 'foo', 'groups' => []],
			['mount_point' => 'other', 'groups' => []],
		]);
	}

	public function testGetFoldersForGroups(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->setGroupPermissions($folderId1, 'g1', 2);

		$folders = $this->manager->getFoldersForGroups(['g1']);
		$this->assertCount(1, $folders);
		$folder = $folders[0];
		$this->assertEquals('foo', $folder->mountPoint);
		$this->assertEquals(2, $folder->permissions);
	}

	/**
	 * @param string[] $groups
	 */
	protected function getUser(array $groups = []): IUser&MockObject {
		$id = uniqid();
		$user = $this->createMock(IUser::class);
		$this->groupManager->expects($this->any())
			->method('getUserGroupIds')
			->willReturn($groups);
		$user->method('getUID')
			->willReturn($id);

		return $user;
	}

	public function testGetFoldersForUserEmpty(): void {
		$folders = $this->manager->getFoldersForUser($this->getUser());
		$this->assertEquals([], $folders);
	}


	public function testGetFoldersForUserSimple(): void {
		$db = $this->createMock(IDBConnection::class);
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader, $this->logger, $this->eventDispatcher, $this->config, $this->userMappingManager, $this->folderStorageManager, $this->appConfig])
			->onlyMethods(['getFoldersForGroups'])
			->getMock();

		$cacheEntry = $this->createMock(CacheEntry::class);

		$folder = new FolderDefinitionWithPermissions(
			1,
			'foo',
			1000,
			false,
			false,
			1,
			2,
			[],
			$cacheEntry,
			31,
		);

		$manager->expects($this->once())
			->method('getFoldersForGroups')
			->willReturn([$folder]);

		$folders = $manager->getFoldersForUser($this->getUser(['g1']));
		$this->assertEquals([$folder], $folders);
	}

	public function testGetFoldersForUserMerge(): void {
		$db = $this->createMock(IDBConnection::class);
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader, $this->logger, $this->eventDispatcher, $this->config, $this->userMappingManager, $this->folderStorageManager, $this->appConfig])
			->onlyMethods(['getFoldersForGroups'])
			->getMock();

		$cacheEntry = $this->createMock(CacheEntry::class);

		$folder1 = new FolderDefinitionWithPermissions(
			1,
			'foo',
			1000,
			false,
			false,
			1,
			2,
			[],
			$cacheEntry,
			3,
		);
		$folder2 = new FolderDefinitionWithPermissions(
			1,
			'foo',
			1000,
			false,
			false,
			1,
			2,
			[],
			$cacheEntry,
			8,
		);
		$merged = new FolderDefinitionWithPermissions(
			1,
			'foo',
			1000,
			false,
			false,
			1,
			2,
			[],
			$cacheEntry,
			8 + 3,
		);

		$manager->expects($this->any())
			->method('getFoldersForGroups')
			->willReturn([$folder1, $folder2]);

		$folders = $manager->getFoldersForUser($this->getUser(['g1', 'g2', 'g3']));
		$this->assertEquals([$merged], $folders);
	}

	public function testGetFolderPermissionsForUserMerge(): void {
		$db = $this->createMock(IDBConnection::class);
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader, $this->logger, $this->eventDispatcher, $this->config, $this->userMappingManager, $this->folderStorageManager, $this->appConfig])
			->onlyMethods(['getFoldersForGroups'])
			->getMock();

		$cacheEntry = $this->createMock(CacheEntry::class);

		$folder1 = new FolderDefinitionWithPermissions(
			1,
			'foo',
			1000,
			false,
			false,
			1,
			2,
			[],
			$cacheEntry,
			3,
		);
		$folder2 = new FolderDefinitionWithPermissions(
			1,
			'foo',
			1000,
			false,
			false,
			1,
			2,
			[],
			$cacheEntry,
			8,
		);

		$manager->expects($this->any())
			->method('getFoldersForGroups')
			->willReturn([$folder1, $folder2]);

		$permissions = $manager->getFolderPermissionsForUser($this->getUser(['g1', 'g2', 'g3']), 1);
		$this->assertEquals(11, $permissions);

		$permissions = $manager->getFolderPermissionsForUser($this->getUser(['g1', 'g2', 'g3']), 2);
		$this->assertEquals(0, $permissions);
	}

	public function testSetFolderQuotaInvalidatesEtag(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId = $this->manager->createFolder('quota-etag-test');
		$this->seedFilecacheForFolder($folderId);

		$folderBefore = $this->manager->getFolder($folderId);
		$this->assertNotNull($folderBefore);
		$etagBefore = $folderBefore->rootCacheEntry->getEtag();

		$this->manager->setFolderQuota($folderId, 1024 * 1024 * 1024);

		$folderAfter = $this->manager->getFolder($folderId);
		$this->assertNotNull($folderAfter);
		$etagAfter = $folderAfter->rootCacheEntry->getEtag();

		$this->assertNotEquals(
			$etagBefore,
			$etagAfter,
			'Etag must change after quota update so desktop clients re-fetch quota-available-bytes via PROPFIND',
		);

		$this->assertSame(
			1024 * 1024 * 1024,
			$folderAfter->quota,
			'Quota value must be persisted correctly',
		);
	}

	public function testQuotaDefaultValue(): void {
		$folderId1 = $this->manager->createFolder('foo');

		$exponent = 3;
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturnCallback(function () use (&$exponent): int {
				return 1024 ** ($exponent++);
			});

		$folder = $this->manager->getFolder($folderId1);
		if (!$folder) {
			throw new \Exception('Folder not found');
		}
		$this->assertEquals(1024 ** 3, $folder->quota);

		$folder = $this->manager->getFolder($folderId1);
		if (!$folder) {
			throw new \Exception('Folder not found');
		}
		$this->assertEquals(1024 ** 4, $folder->quota);
	}
}
