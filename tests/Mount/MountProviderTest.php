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

namespace OCA\GroupFolders\Tests\Mount;

use OC\User\User;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Files\Folder;
use OCP\Files\Storage\IStorageFactory;
use OCP\IGroupManager;
use OCP\IUser;
use Test\TestCase;
use Test\Traits\UserTrait;

class MountProviderTest extends TestCase {
	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var FolderManager|\PHPUnit_Framework_MockObject_MockObject */
	private $folderManager;
	/** @var Folder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;
	/** @var IStorageFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $loader;
	/** @var MountProvider */
	private $mountProvider;

	protected function setUp() {
		parent::setUp();

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->folderManager = $this->createMock(FolderManager::class);
		$this->rootFolder = $this->createMock(Folder::class);
		$this->loader = $this->createMock(IStorageFactory::class);

		$this->rootFolder->expects($this->any())
			->method('get')
			->willReturn($this->rootFolder);

		$this->mountProvider = new MountProvider($this->groupManager, $this->folderManager, function () {
			return $this->rootFolder;
		});
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
		$folders = $this->mountProvider->getFoldersForUser($this->getUser());
		$this->assertEquals([], $folders);
	}

	public function testGetFoldersForUserSimple() {
		$folder = [
			[
				'folder_id' => 1,
				'mount_point' => 'foo',
				'permissions' => 31,
				'quota' => -3
			]
		];

		$this->folderManager->expects($this->once())
			->method('getFoldersForGroup')
			->willReturn($folder);

		$folders = $this->mountProvider->getFoldersForUser($this->getUser(['g1']));
		$this->assertEquals($folder, $folders);
	}

	public function testGetFoldersForUserMerge() {
		$folder1 = [
			[
				'folder_id' => 1,
				'mount_point' => 'foo',
				'permissions' => 3,
				'quota' => 1000
			]
		];
		$folder2 = [
			[
				'folder_id' => 1,
				'mount_point' => 'foo',
				'permissions' => 8,
				'quota' => 1000
			]
		];

		$this->folderManager->expects($this->any())
			->method('getFoldersForGroup')
			->willReturnCallback(function ($group) use ($folder1, $folder2) {
				switch ($group) {
					case 'g1':
						return $folder1;
					case 'g2':
						return $folder2;
					default:
						return [];
				}
			});

		$folders = $this->mountProvider->getFoldersForUser($this->getUser(['g1', 'g2', 'g3']));
		$this->assertEquals([
			[
				'folder_id' => 1,
				'mount_point' => 'foo',
				'permissions' => 11,
				'quota' => 1000
			]
		], $folders);
	}
}

