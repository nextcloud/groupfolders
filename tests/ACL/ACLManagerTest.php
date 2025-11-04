<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\Constants;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class ACLManagerTest extends TestCase {
	private RuleManager&MockObject $ruleManager;
	private IUserMappingManager&MockObject $userMappingManager;
	private IUser&MockObject $user;
	private ACLManager $aclManager;
	private IUserMapping&MockObject $dummyMapping;
	/** @var array<string, list<Rule>> */
	private array $rules = [];

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->ruleManager = $this->createMock(RuleManager::class);
		$this->userMappingManager = $this->createMock(IUserMappingManager::class);
		$this->aclManager = $this->getAclManager();
		$this->dummyMapping = $this->createMapping('dummy');

		$this->ruleManager->method('getRulesForFilesByPath')
			->willReturnCallback(function (IUser $user, int $storageId, array $paths): array {
				// fill with empty in case no rule was found
				$rules = array_fill_keys($paths, []);
				$actualRules = array_filter($this->rules, fn (string $path): bool => array_search($path, $paths) !== false, ARRAY_FILTER_USE_KEY);

				return array_merge($rules, $actualRules);
			});

		$this->ruleManager->method('getRulesForPrefix')
			->willReturnCallback(fn (IUser $user, int $storageId, string $prefix): array => array_filter(
				$this->rules,
				static fn (string $path): bool => $prefix === $path || str_starts_with($path, $prefix . '/'),
				ARRAY_FILTER_USE_KEY,
			));
	}

	private function createMapping(string $id): IUserMapping&MockObject {
		$mapping = $this->createMock(IUserMapping::class);
		$mapping->method('getType')->willReturn('dummy');
		$mapping->method('getId')->willReturn($id);
		$mapping->method('getDisplayName')->willReturn("display name for $id");

		return $mapping;
	}

	private function getAclManager(bool $perUserMerge = false): ACLManager {
		return new ACLManager($this->ruleManager, $this->userMappingManager, $this->user, $perUserMerge);
	}

	public function testGetACLPermissionsForPathNoRules(): void {
		$this->rules = [];
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath(0, 0, 'foo'));
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
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE, $this->aclManager->getACLPermissionsForPath(0, 0, 'foo'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE, $this->aclManager->getACLPermissionsForPath(0, 0, 'foo/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath(0, 0, 'foo/bar/sub'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE - Constants::PERMISSION_READ, $this->aclManager->getACLPermissionsForPath(0, 0, 'foo/blocked'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE - Constants::PERMISSION_READ, $this->aclManager->getACLPermissionsForPath(0, 0, 'foo/blocked2'));
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

		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath(0, 0, '__groupfolders/trash/1/subfolder2.d1700752274/coucou.md'));
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
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE, $aclManager->getACLPermissionsForPath(0, 0, 'foo'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE, $aclManager->getACLPermissionsForPath(0, 0, 'foo/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $aclManager->getACLPermissionsForPath(0, 0, 'foo/bar/sub'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE, $aclManager->getACLPermissionsForPath(0, 0, 'foo/blocked'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE - Constants::PERMISSION_UPDATE - Constants::PERMISSION_READ, $aclManager->getACLPermissionsForPath(0, 0, 'foo/blocked2'));
	}

	public function testGetPermissionsForTree(): void {
		$perUserAclManager = $this->getAclManager(true);

		$this->rules = [
			'foo' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_ALL, Constants::PERMISSION_ALL),
			],
			'foo/bar' => [
				new Rule($this->createMapping('2'), 10, Constants::PERMISSION_DELETE, 0) // remove delete
			],
			'foo/bar/asd' => [
				new Rule($this->createMapping('2'), 10, Constants::PERMISSION_DELETE, Constants::PERMISSION_DELETE) // re-add delete
			],
		];
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $this->aclManager->getPermissionsForTree(0, 0, 'foo'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $this->aclManager->getPermissionsForTree(0, 0, 'foo/bar'));

		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo'));
		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo/bar/asd'));

		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getPermissionsForTree(0, 0, 'foo'));
		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getPermissionsForTree(0, 0, 'foo/bar'));

		$this->rules = [
			'foo2' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_ALL, Constants::PERMISSION_ALL),
			],
			'foo2/bar' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_DELETE, 0) // remove delete
			],
			'foo2/bar/asd' => [
				new Rule($this->createMapping('2'), 10, Constants::PERMISSION_DELETE, Constants::PERMISSION_DELETE) // re-add delete
			],
		];

		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo2'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo2/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo2/bar/asd'));

		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $perUserAclManager->getPermissionsForTree(0, 0, 'foo2'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $perUserAclManager->getPermissionsForTree(0, 0, 'foo2/bar'));

		$this->rules = [
			'foo3' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_ALL, Constants::PERMISSION_ALL),
			],
			'foo3/bar' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_DELETE, 0) // remove delete
			],
			'foo3/bar/asd' => [
				new Rule($this->createMapping('1'), 10, Constants::PERMISSION_DELETE, Constants::PERMISSION_DELETE) // re-add delete
			],
		];

		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo3'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo3/bar'));
		$this->assertEquals(Constants::PERMISSION_ALL, $perUserAclManager->getACLPermissionsForPath(0, 0, 'foo3/bar/asd'));

		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $perUserAclManager->getPermissionsForTree(0, 0, 'foo3'));
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_DELETE, $perUserAclManager->getPermissionsForTree(0, 0, 'foo3/bar'));
	}
}
