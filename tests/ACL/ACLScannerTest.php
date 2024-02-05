<?php

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Storage\Temporary;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCP\Constants;
use Test\TestCase;

/**
 * @group DB
 */
class ACLScannerTest extends TestCase {
	private function getAclManager(array $rules): ACLManager {
		$manager = $this->getMockBuilder(ACLManager::class)
			->disableOriginalConstructor()
			->getMock();
		$manager->method('getACLPermissionsForPath')
			->willReturnCallback(function ($path) use ($rules) {
				return $rules[$path] ?? Constants::PERMISSION_ALL;
			});
		return $manager;
	}

	public function testScanAclStorage() {
		$baseStorage = new Temporary([]);
		$baseStorage->mkdir('foo');
		$baseStorage->mkdir('foo/bar');
		$baseStorage->mkdir('foo/bar/asd');
		$cache = $baseStorage->getCache();
		$baseStorage->getScanner()->scan('');

		$cache->update($cache->getId('foo/bar/asd'), ['size' => -1]);
		$cache->calculateFolderSize('foo/bar');
		$cache->calculateFolderSize('foo');

		$this->assertEquals(-1, $cache->get('foo/bar')->getSize());

		$acls = $this->getAclManager([
			'foo/bar' => 0,
			'foo/bar/asd' => 0,
		]);

		$aclStorage = new ACLStorageWrapper([
			'storage' => $baseStorage,
			'acl_manager' => $acls,
			'in_share' => false,
		]);

		$scanner = $aclStorage->getScanner();
		$aclCache = $aclStorage->getCache();
		$scanner->scan('');

		$this->assertEquals(0, $cache->get('foo/bar')->getSize());

		$this->assertEquals(31, $cache->get('foo/bar')->getPermissions());
		$this->assertEquals(false, $aclCache->get('foo/bar'));
	}
}
