<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Cache\CacheEntry;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchOrder;
use OC\Files\Search\SearchQuery;
use OC\Files\Storage\Temporary;
use OCA\GroupFolders\ACL\ACLCacheWrapper;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchOrder;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class ACLCacheWrapperTest extends TestCase {
	private ACLManager $aclManager;
	private ICache&MockObject $source;
	private ACLCacheWrapper $cache;
	/** @var array<string, int> */
	private array $aclPermissions = [];

	private function makeRule(int $permission): Rule {
		return new Rule($this->createMock(IUserMapping::class), 0, Constants::PERMISSION_ALL, $permission);
	}

	/**
	 * @param string[] $paths
	 * @return array<string, Rule[]>
	 */
	private function getRulesForPaths(IUser $user, int $storageId, array $paths): array {
		return array_combine($paths, array_map(
			fn (string $path): array => isset($this->aclPermissions[$path]) ? [$this->makeRule($this->aclPermissions[$path])] : [],
			$paths
		));
	}

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$user = $this->createMock(IUser::class);
		$ruleManager = $this->createMock(RuleManager::class);
		$ruleManager->method('getRulesForFilesByPath')
			->willReturnCallback($this->getRulesForPaths(...));
		$ruleManager->method('getRulesForPrefix')
			->willReturnCallback(fn (IUser $user, int $storageId, string $prefix): array => array_map(
				fn (int $permission): array => [$this->makeRule($permission)],
				array_filter(
					$this->aclPermissions,
					fn (string $path): bool => str_starts_with($path, $prefix),
					ARRAY_FILTER_USE_KEY,
				))
			);
		$mappingManager = $this->createMock(IUserMappingManager::class);

		$this->aclManager = new ACLManager($ruleManager, $mappingManager, $user);

		$this->source = $this->createMock(ICache::class);
		$this->source->method('getNumericStorageId')
			->willReturn(1);
		$this->cache = new ACLCacheWrapper($this->source, $this->aclManager, 0, false);
	}

	public function testHideNonRead(): void {
		$this->source->method('getFolderContentsById')
			->willReturn([
				new CacheEntry([
					'path' => 'foo/f1',
					'permissions' => Constants::PERMISSION_ALL
				]),
				new CacheEntry([
					'path' => 'foo/f2',
					'permissions' => Constants::PERMISSION_ALL
				]),
				new CacheEntry([
					'path' => 'foo/f3',
					'permissions' => Constants::PERMISSION_ALL
				]),
			]);
		$this->aclPermissions['foo/f1'] = Constants::PERMISSION_ALL;
		$this->aclPermissions['foo/f2'] = Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE;
		$this->aclPermissions['foo/f3'] = Constants::PERMISSION_ALL - Constants::PERMISSION_READ;

		$result = $this->cache->getFolderContentsById(0);

		$expected = [
			new CacheEntry([
				'path' => 'foo/f1',
				'permissions' => Constants::PERMISSION_ALL,
				'scan_permissions' => Constants::PERMISSION_ALL
			]),
			new CacheEntry([
				'path' => 'foo/f2',
				'permissions' => Constants::PERMISSION_ALL - Constants::PERMISSION_UPDATE,
				'scan_permissions' => Constants::PERMISSION_ALL
			])
		];

		$this->assertEquals($expected, $result);
	}

	public function testSearchNonRead(): void {
		$sourceStorage = new Temporary([]);
		$sourceStorage->mkdir('foo');
		$sourceStorage->touch('foo/test1.txt', 100);
		$sourceStorage->touch('foo/test2.txt', 101);
		$sourceStorage->touch('foo/test3.txt', 102);
		$sourceStorage->mkdir('bar');
		$sourceStorage->touch('bar/test1.txt', 200);
		$sourceStorage->touch('bar/test2.txt', 201);
		$sourceStorage->touch('bar/test3.txt', 202);
		$sourceStorage->getScanner()->scan('');

		$cache = new ACLCacheWrapper($sourceStorage->getCache(), $this->aclManager, 0, false);

		$query = new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', '%test%'),
			2,
			0,
			[new SearchOrder(ISearchOrder::DIRECTION_DESCENDING, 'mtime')]
		);
		$result = $cache->searchQuery($query);
		$paths = array_map(fn (ICacheEntry $entry) => $entry->getPath(), $result);

		$this->assertEquals([
			'bar/test3.txt',
			'bar/test2.txt'
		], $paths);

		$this->aclPermissions['bar'] = 0;

		$result = $cache->searchQuery($query);
		$paths = array_map(fn (ICacheEntry $entry) => $entry->getPath(), $result);

		$this->assertEquals([
			'foo/test3.txt',
			'foo/test2.txt'
		], $paths);
	}

	/** @var array<string, list<Rule>> rules of the search integration tests, by path */
	private array $searchRules = [];

	/**
	 * @param string[] $paths
	 * @return array<string, list<Rule>>
	 */
	private function getSearchRulesForPaths(IUser $user, int $storageId, array $paths): array {
		return array_combine($paths, array_map(fn (string $path): array => $this->searchRules[$path] ?? [], $paths));
	}

	/**
	 * @return array<string, list<Rule>>
	 */
	private function getSearchRulesForPrefix(IUser $user, int $storageId, string $prefix): array {
		return array_filter($this->searchRules, fn (string $path): bool => str_starts_with($path, $prefix), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * @param array<string, list<Rule>> $rules
	 */
	private function aclManagerForRules(array $rules, bool $perUserMerge): ACLManager {
		$this->searchRules = $rules;
		$ruleManager = $this->createMock(RuleManager::class);
		$ruleManager->method('getRulesForFilesByPath')
			->willReturnCallback($this->getSearchRulesForPaths(...));
		$ruleManager->method('getRulesForPrefix')
			->willReturnCallback($this->getSearchRulesForPrefix(...));

		return new ACLManager($ruleManager, $this->createMock(IUserMappingManager::class), $this->createMock(IUser::class), $perUserMerge);
	}

	private function mapping(string $id): IUserMapping&MockObject {
		$mapping = $this->createMock(IUserMapping::class);
		$mapping->method('getType')->willReturn('group');
		$mapping->method('getId')->willReturn($id);
		return $mapping;
	}

	/**
	 * @return list<string>
	 */
	private function searchPaths(ACLManager $aclManager): array {
		$sourceStorage = new Temporary([]);
		$sourceStorage->mkdir('foo');
		$sourceStorage->mkdir('foo/sub');
		$sourceStorage->touch('foo/sub/test1.txt', 300);
		$sourceStorage->touch('foo/test2.txt', 200);
		$sourceStorage->mkdir('bar');
		$sourceStorage->touch('bar/test3.txt', 100);
		$sourceStorage->getScanner()->scan('');

		$cache = new ACLCacheWrapper($sourceStorage->getCache(), $aclManager, 0, false);
		$query = new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', '%test%'),
			10,
			0,
			[new SearchOrder(ISearchOrder::DIRECTION_DESCENDING, 'mtime')]
		);

		return array_values(array_map(fn (ICacheEntry $entry): string => $entry->getPath(), $cache->searchQuery($query)));
	}

	public function testSearchReadableChildOfForbiddenParent(): void {
		// "foo" denies everything, "foo/sub" gives read back: files below "foo/sub" are readable and must be found
		$a = $this->mapping('a');
		$aclManager = $this->aclManagerForRules([
			'foo' => [new Rule($a, 0, Constants::PERMISSION_ALL, 0)],
			'foo/sub' => [new Rule($a, 0, Constants::PERMISSION_ALL, Constants::PERMISSION_ALL)],
		], false);

		$this->assertEquals(['foo/sub/test1.txt', 'bar/test3.txt'], $this->searchPaths($aclManager));
	}

	public function testSearchPerUserInheritanceRestoresRead(): void {
		// group "a" reads "foo", group "b" denies read on "foo/sub": with acl-inherit-per-user the read of "a" is kept
		// on "foo/sub" (the path is readable and must be found); with the default inheritance "foo/sub" is not readable
		$rules = [
			'foo' => [new Rule($this->mapping('a'), 0, Constants::PERMISSION_ALL, Constants::PERMISSION_ALL)],
			'foo/sub' => [new Rule($this->mapping('b'), 0, Constants::PERMISSION_READ, 0)],
		];

		$this->assertEquals(['foo/sub/test1.txt', 'foo/test2.txt', 'bar/test3.txt'], $this->searchPaths($this->aclManagerForRules($rules, true)));
		$this->assertEquals(['foo/test2.txt', 'bar/test3.txt'], $this->searchPaths($this->aclManagerForRules($rules, false)));
	}
}
