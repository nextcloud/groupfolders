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

use OC\Files\Storage\StorageFactory;
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

		$this->mountProvider = new MountProvider(
			$this->groupManager,
			$this->folderManager,
			new StorageFactory(),
			function () {
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
}

