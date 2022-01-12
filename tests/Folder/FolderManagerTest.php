<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\Tests\Folder;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\Files\IMimeTypeLoader;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use Test\TestCase;

/**
 * @group DB
 */
class FolderManagerTest extends TestCase {
	/** @var FolderManager */
	private $manager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IMimeTypeLoader */
	private $mimeLoader;

	protected function setUp(): void {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->mimeLoader = $this->createMock(IMimeTypeLoader::class);
		$this->manager = new FolderManager(\OC::$server->getDatabaseConnection(), $this->groupManager, $this->mimeLoader);
		$this->clean();
	}

	private function clean() {
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('group_folders')->execute();

		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('group_folders_groups')->execute();
	}

	private function assertHasFolders($folders) {
		$existingFolders = array_values($this->manager->getAllFolders());
		usort($existingFolders, function ($a, $b) {
			return strcmp($a['mount_point'], $b['mount_point']);
		});
		usort($folders, function ($a, $b) {
			return strcmp($a['mount_point'], $b['mount_point']);
		});

		foreach ($folders as &$folder) {
			if (!isset($folder['size'])) {
				$folder['size'] = 0;
			}
			if (!isset($folder['quota'])) {
				$folder['quota'] = -3;
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

	public function testCreateFolder() {
		$this->manager->createFolder('foo');

		$this->assertHasFolders([
			['mount_point' => 'foo', 'groups' => []]
		]);
	}

	public function testSetMountpoint() {
		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->createFolder('bar');

		$this->manager->setMountPoint($folderId1, 'foo2');

		$this->assertHasFolders([
			['mount_point' => 'foo2', 'groups' => []],
			['mount_point' => 'bar', 'groups' => []]
		]);
	}

	public function testAddApplicable() {
		$folderId1 = $this->manager->createFolder('foo');
		$folderId2 = $this->manager->createFolder('bar');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->addApplicableGroup($folderId2, 'g1');
		$this->manager->addApplicableGroup($folderId2, 'g3');

		$this->assertHasFolders([
			['mount_point' => 'foo', 'groups' => ['g1' => Constants::PERMISSION_ALL, 'g2' => Constants::PERMISSION_ALL]],
			['mount_point' => 'bar', 'groups' => ['g1' => Constants::PERMISSION_ALL, 'g3' => Constants::PERMISSION_ALL]],
		]);
	}

	public function testSetPermissions() {
		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->setGroupPermissions($folderId1, 'g1', 2);

		$this->assertHasFolders([
			['mount_point' => 'foo', 'groups' => ['g1' => 2, 'g2' => Constants::PERMISSION_ALL]],
		]);
	}

	public function testRemoveApplicable() {
		$folderId1 = $this->manager->createFolder('foo');
		$folderId2 = $this->manager->createFolder('bar');
		$this->manager->addApplicableGroup($folderId1, 'g1');
		$this->manager->addApplicableGroup($folderId1, 'g2');
		$this->manager->addApplicableGroup($folderId2, 'g1');
		$this->manager->addApplicableGroup($folderId2, 'g3');

		$this->manager->removeApplicableGroup($folderId1, 'g1');

		$this->assertHasFolders([
			['mount_point' => 'foo', 'groups' => ['g2' => Constants::PERMISSION_ALL]],
			['mount_point' => 'bar', 'groups' => ['g1' => Constants::PERMISSION_ALL, 'g3' => Constants::PERMISSION_ALL]],
		]);
	}

	public function testRemoveFolder() {
		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->createFolder('bar');

		$this->manager->removeFolder($folderId1);

		$this->assertHasFolders([
			['mount_point' => 'bar', 'groups' => []]
		]);
	}

	public function testRenameFolder() {
		$folderId1 = $this->manager->createFolder('foo');
		$this->manager->createFolder('other');

		$this->manager->renameFolder($folderId1, 'bar');

		$this->assertHasFolders([
			['mount_point' => 'bar', 'groups' => []],
			['mount_point' => 'other', 'groups' => []],
		]);
	}

	public function testSetACL() {
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

	public function testGetFoldersForGroup() {
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

	public function testGetFoldersForGroups() {
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
	 * @return \PHPUnit_Framework_MockObject_MockObject|IUser
	 */
	protected function getUser($groups = []) {
		$user = $this->createMock(IUser::class);
		$this->groupManager->expects($this->any())
			->method('getUserGroupIds')
			->willReturn($groups);

		return $user;
	}

	public function testGetFoldersForUserEmpty() {
		$folders = $this->manager->getFoldersForUser($this->getUser());
		$this->assertEquals([], $folders);
	}


	public function testGetFoldersForUserSimple() {
		$db = $this->createMock(IDBConnection::class);
		/** @var FolderManager|\PHPUnit_Framework_MockObject_MockObject $manager */
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader])
			->setMethods(['getFoldersForGroups'])
			->getMock();

		$folder = [
			'folder_id' => 1,
			'mount_point' => 'foo',
			'permissions' => 31,
			'quota' => -3
		];

		$manager->expects($this->once())
			->method('getFoldersForGroups')
			->willReturn([$folder]);

		$folders = $manager->getFoldersForUser($this->getUser(['g1']));
		$this->assertEquals([$folder], $folders);
	}

	public function testGetFoldersForUserMerge() {
		$db = $this->createMock(IDBConnection::class);
		/** @var FolderManager|\PHPUnit_Framework_MockObject_MockObject $manager */
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader])
			->setMethods(['getFoldersForGroups'])
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

	public function testGetFolderPermissionsForUserMerge() {
		$db = $this->createMock(IDBConnection::class);
		/** @var FolderManager|\PHPUnit_Framework_MockObject_MockObject $manager */
		$manager = $this->getMockBuilder(FolderManager::class)
			->setConstructorArgs([$db, $this->groupManager, $this->mimeLoader])
			->setMethods(['getFoldersForGroups'])
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
}
