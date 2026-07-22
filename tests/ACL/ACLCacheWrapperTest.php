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
}
