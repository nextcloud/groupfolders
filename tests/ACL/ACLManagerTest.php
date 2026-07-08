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
	/** @var list<string> paths that were resolved through a query rather than the cache */
	private array $requestedPaths = [];

	#[\Override]
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
				/** @var string[] $paths */
				$this->requestedPaths = array_values(array_merge($this->requestedPaths, $paths));
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

	public function testPreloadRulesForFolderPopulatesCache(): void {
		// no per-path rules are available: anything resolved by a query returns empty
		$this->rules = [];
		$childPath = '__groupfolders/trash/1/subfolder2.d1700752274';
		$rule = new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, 0); // deny share

		$this->ruleManager->expects($this->once())
			->method('getRulesForFilesByParent')
			->willReturn([$childPath => [$rule]]);

		$this->requestedPaths = [];
		$this->aclManager->preloadRulesForFolder(0, 1);

		$permissions = $this->aclManager->getACLPermissionsForPath(0, 0, $childPath);

		// the rule supplied through preload must have been applied straight from the cache ...
		$this->assertEquals(Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE, $permissions);
		// ... without re-querying the leaf path that was preloaded
		$this->assertNotContains($childPath, $this->requestedPaths);
	}

	public function testRuleCacheIsScopedPerStorage(): void {
		// Folders using a separate storage restart their paths at the storage root
		// ("files/", "trash/", "versions/"), so two folders can have a file at the
		// same path under different storage ids. As one ACLManager (and its cache)
		// is now shared for the whole request, the cache must neither let one
		// storage's rules leak into another nor stop caching within a storage.
		$ruleManager = $this->createMock(RuleManager::class);
		$aclManager = new ACLManager($ruleManager, $this->userMappingManager, $this->user);

		$path = 'files/file';
		$denyShare = new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, 0);
		$denyUpdate = new Rule($this->dummyMapping, 10, Constants::PERMISSION_UPDATE, 0);

		$queriedStorages = [];
		$ruleManager->method('getRulesForFilesByPath')
			->willReturnCallback(function (IUser $user, int $storageId, array $paths) use ($path, $denyShare, $denyUpdate, &$queriedStorages): array {
				/** @var string[] $paths */
				$queriedStorages[] = $storageId;
				$rules = array_fill_keys($paths, []);
				if (in_array($path, $paths, true)) {
					$rules[$path] = [$storageId === 1 ? $denyShare : $denyUpdate];
				}

				return $rules;
			});

		// resolving on storage 1 caches its rules for "files/file"
		$this->assertEquals(
			Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE,
			$aclManager->getACLPermissionsForPath(0, 1, $path),
		);
		// the same path on storage 2 must use storage 2's rules, not the cached storage-1 entry
		$this->assertEquals(
			Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE,
			$aclManager->getACLPermissionsForPath(0, 2, $path),
		);
		// a second lookup on storage 1 is still served from the cache ...
		$this->assertEquals(
			Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE,
			$aclManager->getACLPermissionsForPath(0, 1, $path),
		);
		// ... i.e. scoping the key by storage did not turn same-storage hits into misses
		$this->assertSame(1, count(array_filter($queriedStorages, fn (int $storageId): bool => $storageId === 1)));
	}

	public function testPreloadRuleCacheIsScopedPerStorage(): void {
		// preloadRulesForFolder warms the cache for one storage; a same-named path
		// on another storage must not be resolved from that preloaded entry.
		$path = '__groupfolders/1/file';
		$denyShare = new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, 0);

		$this->ruleManager->expects($this->once())
			->method('getRulesForFilesByParent')
			->willReturn([$path => [$denyShare]]);

		$this->rules = [];
		$this->requestedPaths = [];
		$this->aclManager->preloadRulesForFolder(1, 1); // warm storage 1

		// storage 2 must not pick up storage 1's preloaded rule ...
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath(0, 2, $path));
		// ... it must actually query for its own rules
		$this->assertContains($path, $this->requestedPaths);
	}

	public function testGetRulesByFileIdsCacheIsScopedPerStorage(): void {
		// getRulesByFileIds caches the resolved rules per storage; a same-named path
		// on another storage must not be resolved from that cached entry.
		$path = 'files/file';
		$denyShare = new Rule($this->dummyMapping, 10, Constants::PERMISSION_SHARE, 0);

		$this->ruleManager->method('getRulesForFilesByIds')
			->willReturn([1 => [$path => [$denyShare]]]);

		$this->rules = [];
		$this->requestedPaths = [];
		$this->aclManager->getRulesByFileIds([10]); // warm storage 1

		// storage 2 must not pick up storage 1's cached rule ...
		$this->assertEquals(Constants::PERMISSION_ALL, $this->aclManager->getACLPermissionsForPath(0, 2, $path));
		// ... it must actually query for its own rules
		$this->assertContains($path, $this->requestedPaths);
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
