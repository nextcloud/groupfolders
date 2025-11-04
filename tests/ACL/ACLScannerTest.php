<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Cache\Cache;
use OC\Files\Storage\Temporary;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCP\Constants;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class ACLScannerTest extends TestCase {
	private function getAclManager(array $rules): ACLManager&MockObject {
		$manager = $this->getMockBuilder(ACLManager::class)
			->disableOriginalConstructor()
			->getMock();
		$manager->method('getACLPermissionsForPath')
			->willReturnCallback(fn (int $folderId, int $storageId, string $path) => $rules[$path] ?? Constants::PERMISSION_ALL);

		return $manager;
	}

	public function testScanAclStorage(): void {
		$baseStorage = new Temporary([]);
		$baseStorage->mkdir('foo');
		$baseStorage->mkdir('foo/bar');
		$baseStorage->mkdir('foo/bar/asd');
		/** @var Cache $cache */
		$cache = $baseStorage->getCache();
		$baseStorage->getScanner()->scan('');

		$cache->update($cache->getId('foo/bar/asd'), ['size' => -1]);
		$cache->calculateFolderSize('foo/bar');
		$cache->calculateFolderSize('foo');

		/** @psalm-suppress PossiblyFalseReference */
		$this->assertEquals(-1, $cache->get('foo/bar')->getSize());

		$acls = $this->getAclManager([
			'foo/bar' => 0,
			'foo/bar/asd' => 0,
		]);

		$aclStorage = new ACLStorageWrapper([
			'storage' => $baseStorage,
			'acl_manager' => $acls,
			'in_share' => false,
			'folder_id' => 0,
			'storage_id' => $cache->getNumericStorageId(),
		]);

		$scanner = $aclStorage->getScanner();
		$aclCache = $aclStorage->getCache();
		$scanner->scan('');

		/** @psalm-suppress PossiblyFalseReference */
		$this->assertEquals(0, $cache->get('foo/bar')->getSize());

		/** @psalm-suppress PossiblyFalseReference */
		$this->assertEquals(31, $cache->get('foo/bar')->getPermissions());
		$this->assertEquals(false, $aclCache->get('foo/bar'));
	}
}
