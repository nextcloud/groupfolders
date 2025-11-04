<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Cache\CacheEntry;
use OCA\GroupFolders\ACL\ACLCacheWrapper;
use OCA\GroupFolders\ACL\ACLManager;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class ACLCacheWrapperTest extends TestCase {
	private ACLManager&MockObject $aclManager;
	private ICache&MockObject $source;
	private ACLCacheWrapper $cache;
	private array $aclPermissions = [];

	protected function setUp(): void {
		parent::setUp();

		$this->aclManager = $this->createMock(ACLManager::class);
		$this->aclManager->method('getACLPermissionsForPath')
			->willReturnCallback(fn (int $folderId, int $storageId, string $path) => $this->aclPermissions[$path] ?? Constants::PERMISSION_ALL);
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
}
