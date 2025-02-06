<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Folder;

use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeLoader;
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
 */
class FolderManagerTest extends TestCase {
	private FolderManager $manager;
	private IGroupManager&MockObject $groupManager;
	private IMimeTypeLoader&MockObject $mimeLoader;
	private LoggerInterface&MockObject $logger;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IConfig&MockObject $config;
	private IUserMappingManager&MockObject $userMappingManager;

	protected function setUp(): void {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->mimeLoader = $this->createMock(IMimeTypeLoader::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userMappingManager = $this->createMock(IUserMappingManager::class);
		$this->manager = new FolderManager(
			Server::get(IDBConnection::class),
			$this->groupManager,
			$this->mimeLoader,
			$this->logger,
			$this->eventDispatcher,
			$this->config,
			$this->userMappingManager,
		);
		$this->clean();
	}

	private function clean(): void {
		$query = Server::get(IDBConnection::class)->getQueryBuilder();
		$query->delete('group_folders')->executeStatement();

		$query = Server::get(IDBConnection::class)->getQueryBuilder();
		$query->delete('group_folders_groups')->executeStatement();
	}

	private function assertHasFolders(array $folders): void {
		$existingFolders = array_values($this->manager->getAllFolders());
		usort($existingFolders, fn (array $a, array $b): int => strcmp($a['mount_point'], $b['mount_point']));
		usort($folders, fn (array $a, array $b): int => strcmp($a['mount_point'], $b['mount_point']));

		foreach ($folders as &$folder) {
			if (!isset($folder['size'])) {
				$folder['size'] = 0;
			}

			if (!isset($folder['quota'])) {
				$folder['quota'] = FileInfo::SPACE_UNLIMITED;
			}

			if (!isset($folder['acl'])) {
				$folder['acl'] = false;
			}
		}

		foreach ($existingFolders as &$existingFolder) {
			unset($existingFolder['id']);
		}

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
					'groups' =>
						[
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
					'groups' =>
						[
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
					'groups' =>
						[
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
					'groups' =>
						[
							'g2' => [
								'displayName' => 'g2',
								'permissions' => Constants::PERMISSION_ALL,
								'type' => 'group'
							]
						]
				],
				[
					'mount_point' => 'bar',
					'groups' =>
						[
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

	public function testGetFoldersForGroup(): void {
		$this->config->expects($this->any())
			->method('getSystemValueInt')
			->with('groupfolders.quota.default', FileInfo::SPACE_UNLIMITED)
			->willReturn(FileInfo::SPACE_UNLIMITED);

		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->setGroupPermissions($folderId1, 'g1', 2);

		$folders = $this->manager->getFoldersForGroup('g1');
		$this->assertCount(1, $folders);
		$folder = $folders[0];
		$this->assertEquals('foo', $folder['mount_point']);
		$this->assertEquals(2, $folder['permissions']);
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
		$this->assertEquals('foo', $folder['mount_point']);
		$this->assertEquals(2, $folder['permissions']);
	}

	/**
	 * @param string[] $groups
	 */
	protected function getUser($groups = []): IUser&MockObject {
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
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader, $this->logger, $this->eventDispatcher, $this->config, $this->userMappingManager])
			->onlyMethods(['getFoldersForGroups'])
			->getMock();

		$folder = [
			'folder_id' => 1,
			'mount_point' => 'foo',
			'permissions' => 31,
			'quota' => FileInfo::SPACE_UNLIMITED,
		];

		$manager->expects($this->once())
			->method('getFoldersForGroups')
			->willReturn([$folder]);

		$folders = $manager->getFoldersForUser($this->getUser(['g1']));
		$this->assertEquals([$folder], $folders);
	}

	public function testGetFoldersForUserMerge(): void {
		$db = $this->createMock(IDBConnection::class);
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader, $this->logger, $this->eventDispatcher, $this->config, $this->userMappingManager])
			->onlyMethods(['getFoldersForGroups'])
			->getMock();

		$folder1 = [
			'folder_id' => 1,
			'mount_point' => 'foo',
			'permissions' => 3,
			'quota' => 1000
		];
		$folder2 = [
			'folder_id' => 1,
			'mount_point' => 'foo',
			'permissions' => 8,
			'quota' => 1000
		];

		$manager->expects($this->any())
			->method('getFoldersForGroups')
			->willReturn([$folder1, $folder2]);

		$folders = $manager->getFoldersForUser($this->getUser(['g1', 'g2', 'g3']));
		$this->assertEquals([
			[
				'folder_id' => 1,
				'mount_point' => 'foo',
				'permissions' => 11,
				'quota' => 1000
			]
		], $folders);
	}

	public function testGetFolderPermissionsForUserMerge(): void {
		$db = $this->createMock(IDBConnection::class);
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader, $this->logger, $this->eventDispatcher, $this->config, $this->userMappingManager])
			->onlyMethods(['getFoldersForGroups'])
			->getMock();

		$folder1 = [
			'folder_id' => 1,
			'mount_point' => 'foo',
			'permissions' => 3,
			'quota' => 1000
		];
		$folder2 = [
			'folder_id' => 1,
			'mount_point' => 'foo',
			'permissions' => 8,
			'quota' => 1000
		];

		$manager->expects($this->any())
			->method('getFoldersForGroups')
			->willReturn([$folder1, $folder2]);

		$permissions = $manager->getFolderPermissionsForUser($this->getUser(['g1', 'g2', 'g3']), 1);
		$this->assertEquals(11, $permissions);

		$permissions = $manager->getFolderPermissionsForUser($this->getUser(['g1', 'g2', 'g3']), 2);
		$this->assertEquals(0, $permissions);
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

		/** @var array $folder */
		$folder = $this->manager->getFolder($folderId1);
		$this->assertEquals(1024 ** 3, $folder['quota']);

		/** @var array $folder */
		$folder = $this->manager->getFolder($folderId1);
		$this->assertEquals(1024 ** 4, $folder['quota']);
	}
}
