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
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\IUser;
use Test\TestCase;

class ACLManagerTest extends TestCase {
	/** @var RuleManager|\PHPUnit_Framework_MockObject_MockObject */
	private $ruleManager;
	/** @var IUser */
	private $user;
	/** @var ACLManager */
	private $aclManager;
	/** @var IUserMapping */
	private $dummyMapping;
	/** @var Rule[] */
	private $rules = [];

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->ruleManager = $this->createMock(RuleManager::class);
		$rootMountPoint = $this->createMock(IMountPoint::class);
		$rootMountPoint->method('getNumericStorageId')
			->willReturn(1);
		$rootFolder = $this->createMock(IRootFolder::class);
		$rootFolder->method('getMountPoint')
			->willReturn($rootMountPoint);
		$this->aclManager = new ACLManager($this->ruleManager, $this->user, function () use ($rootFolder) {
			return $rootFolder;
		});
		$this->dummyMapping = $this->createMock(IUserMapping::class);

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

	public function testGetACLPermissionsForPathNoRules() {
		$this->rules = [];
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath('foo'));
	}

	public function testGetACLPermissionsForPath() {
		$this->rules = [
			'foo' => [
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_READ + Constants::PERMISSION_UPDATE, Constants::PERMISSION_READ), // read only
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, 0) // deny share
			],
			'foo/bar' => [
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_UPDATE, Constants::PERMISSION_UPDATE) // add write
			],
			'foo/bar/sub' => [
				new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, Constants::PERMISSION_SHARE) // add share
			]
		];
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE, $this->aclManager->getACLPermissionsForPath('foo'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE, $this->aclManager->getACLPermissionsForPath('foo/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath('foo/bar/sub'));
	}
}
