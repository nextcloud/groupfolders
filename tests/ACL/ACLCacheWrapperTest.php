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

use OC\Files\Cache\CacheEntry;
use OCA\GroupFolders\ACL\ACLCacheWrapper;
use OCA\GroupFolders\ACL\ACLManager;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\IDBConnection;
use Test\TestCase;

class ACLCacheWrapperTest extends TestCase {
	/** @var ACLManager|\PHPUnit_Framework_MockObject_MockObject */
	private $aclManager;
	/** @var ICache|\PHPUnit_Framework_MockObject_MockObject */
	private $source;
	/** @var ACLCacheWrapper */
	private $cache;
	private $aclPermissions = [];

	protected function setUp(): void {
		parent::setUp();

		\OC::$server->registerService(IDBConnection::class, function () {
			return $this->createMock(IDBConnection::class);
		});

		$this->aclManager = $this->createMock(ACLManager::class);
		$this->aclManager->method('getACLPermissionsForPath')
			->willReturnCallback(function (string $path) {
				return isset($this->aclPermissions[$path]) ? $this->aclPermissions[$path] : Constants::PERMISSION_ALL;
			});
		$this->source = $this->createMock(ICache::class);
		$this->cache = new ACLCacheWrapper($this->source, $this->aclManager, false);
	}

	public function testHideNonRead() {
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
