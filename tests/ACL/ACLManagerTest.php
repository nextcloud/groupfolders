<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
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

namespace OCA\groupfolders\tests\ACL;

use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\Trash\TrashManager;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ACLManagerTest extends TestCase {
	private RuleManager $ruleManager;
	private TrashManager $trashManager;
	private LoggerInterface $logger;
	private IUser $user;
	private ACLManager $aclManager;
	private IUserMapping $dummyMapping;
	/** @var Rule[] */
	private array $rules = [];

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->ruleManager = $this->createMock(RuleManager::class);
		$this->trashManager = $this->createMock(TrashManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->aclManager = $this->getAclManager();
		$this->dummyMapping = $this->createMapping('dummy');

		$this->ruleManager->method('getRulesForFilesByPath')
			->willReturnCallback(function (IUser $user, int $storageId, array $paths) {
				// fill with empty in case no rule was found
				$rules = array_fill_keys($paths, []);
				$actualRules = array_filter($this->rules, function (string $path) use ($paths) {
					return array_search($path, $paths) !== false;
				}, ARRAY_FILTER_USE_KEY);

				return array_merge($rules, $actualRules);
			});
	}

	private function createMapping(string $id): IUserMapping {
		$mapping = $this->createMock(IUserMapping::class);
		$mapping->method('getType')->willReturn('dummy');
		$mapping->method('getId')->willReturn($id);
		$mapping->method('getDisplayName')->willReturn("display name for $id");
		return $mapping;
	}

	private function getAclManager(bool $perUserMerge = false): ACLManager {
		$rootMountPoint = $this->createMock(IMountPoint::class);
		$rootMountPoint->method('getNumericStorageId')
			->willReturn(1);
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getMountPoint')
			->willReturn($rootMountPoint);

		return new ACLManager($this->ruleManager, $this->trashManager, $this->logger, $this->user, function () use ($rootFolder) {
			return $rootFolder;
		}, null, $perUserMerge);
	}

	public function testGetACLPermissionsForPathNoRules(): void {
		$this->rules = [];
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath('foo'));
	}

	public function testGetACLPermissionsForPath(): void {
		$this->rules = [
			'foo' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_READ + Constants::PERMISSION_UPDATE, Constants::PERMISSION_READ), // read only
				new Rule($this->createMapping('2'), 10, Constants::PERMISSION_SHARE, 0) // deny share
			],
			'foo/bar' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_UPDATE, Constants::PERMISSION_UPDATE) // add write
			],
			'foo/bar/sub' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_SHARE, Constants::PERMISSION_SHARE) // add share
			],
			'foo/blocked' => [
				new Rule($this->createMapping('2'), 10, Constants::PERMISSION_READ, 0) // remove read
			],
			'foo/blocked2' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_READ, 0) // remove read
			],
		];
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE, $this->aclManager->getACLPermissionsForPath('foo'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE, $this->aclManager->getACLPermissionsForPath('foo/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath('foo/bar/sub'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE - Constants::PERMISSION_READ, $this->aclManager->getACLPermissionsForPath('foo/blocked'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE - Constants::PERMISSION_READ, $this->aclManager->getACLPermissionsForPath('foo/blocked2'));
	}

	public function testGetACLPermissionsForPathInTrashbin(): void {
		$this->rules = [
			'__groupfolders/1' => [
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_READ + Constants::PERMISSION_UPDATE, Constants::PERMISSION_READ), // read only
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, 0) // deny share
			],
			'__groupfolders/1/subfolder' => [
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_UPDATE, Constants::PERMISSION_UPDATE) // add write
			],
			'__groupfolders/trash/1/subfolder2.d1700752274' => [
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, Constants::PERMISSION_SHARE) // add share
			]
		];

		$this->trashManager
			->expects($this->once())
			->method('getTrashItemByFileName')
			->with(1, 'subfolder2', 1700752274)
			->willReturn([
				'trash_id' => 3,
				'name' => 'subfolder2',
				'deleted_time' => '1700752274',
				'original_location' => 'subfolder/subfolder2',
				'folder_id' => '1',
			]);
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath('__groupfolders/trash/1/subfolder2.d1700752274/coucou.md'));
	}



	public function testGetACLPermissionsForPathPerUserMerge(): void {
		$aclManager = $this->getAclManager(true);
		$this->rules = [
			'foo' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_READ + Constants::PERMISSION_UPDATE, Constants::PERMISSION_READ), // read only
				new Rule($this->createMapping('2'), 10, Constants::PERMISSION_SHARE, 0) // deny share
			],
			'foo/bar' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_UPDATE, Constants::PERMISSION_UPDATE) // add write
			],
			'foo/bar/sub' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_SHARE, Constants::PERMISSION_SHARE) // add share
			],
			'foo/blocked' => [
				new Rule($this->createMapping('2'), 10, Constants::PERMISSION_READ, 0) // remove read
			],
			'foo/blocked2' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_READ, 0) // remove read
			],
		];
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE, $aclManager->getACLPermissionsForPath('foo'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE, $aclManager->getACLPermissionsForPath('foo/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $aclManager->getACLPermissionsForPath('foo/bar/sub'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE, $aclManager->getACLPermissionsForPath('foo/blocked'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE - Constants::PERMISSION_READ, $aclManager->getACLPermissionsForPath('foo/blocked2'));
	}
}
