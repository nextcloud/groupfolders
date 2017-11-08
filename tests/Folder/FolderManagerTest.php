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
use Test\TestCase;

/**
 * @group DB
 */
class FolderManagerTest extends TestCase {
	/** @var FolderManager */
	private $manager;

	protected function setUp() {
		parent::setUp();

		$this->manager = new FolderManager(\OC::$server->getDatabaseConnection());
		$this->clean();
	}

	private function clean() {
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('group_folders')->execute();

		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->delete('group_folders_applicable')->execute();
	}

	private function assertHasFolders($folders) {
		$existingFolders = array_values($this->manager->getAllFolders());
		sort($existingFolders);
		sort($folders);

		foreach ($folders as &$folder) {
			if (!isset($folder['size'])) {
				$folder['size'] = 0;
			}
			if (!isset($folder['quota'])) {
				$folder['quota'] = -3;
			}
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
}
