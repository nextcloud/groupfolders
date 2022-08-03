<?php

declare(strict_types=1);
/**
 * @copyright SPDX-FileCopyrightText: 2019 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Storage\Temporary;
use Test\TestCase;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCP\Constants;
use OCP\Files\Storage\IStorage;

class ACLStorageWrapperTest extends TestCase {
	/** @var ACLManager|\PHPUnit_Framework_MockObject_MockObject */
	private $aclManager;
	/** @var IStorage */
	private $source;
	/** @var ACLStorageWrapper */
	private $storage;
	private $aclPermissions = [];

	protected function setUp(): void {
		parent::setUp();

		$this->aclManager = $this->createMock(ACLManager::class);
		$this->aclManager->method('getACLPermissionsForPath')
			->willReturnCallback(function (string $path) {
				return isset($this->aclPermissions[$path]) ? $this->aclPermissions[$path] : Constants::PERMISSION_ALL;
			});
		$this->source = new Temporary([]);
		$this->storage = new ACLStorageWrapper([
			'storage' => $this->source,
			'acl_manager' => $this->aclManager,
			'in_share' => false
		]);
	}

	public function testNoReadImpliesNothing() {
		$this->source->mkdir('foo');
		$this->aclPermissions['foo'] = Constants::PERMISSION_ALL - Constants::PERMISSION_READ;

		$this->assertEquals(false, $this->storage->isUpdatable('foo'));
		$this->assertEquals(false, $this->storage->isCreatable('foo'));
		$this->assertEquals(false, $this->storage->isDeletable('foo'));
		$this->assertEquals(false, $this->storage->isSharable('foo'));
	}

	public function testOpenDir() {
		$this->source->mkdir('foo');
		$this->source->touch('foo/file1');
		$this->source->touch('foo/file2');
		$this->source->touch('foo/file3');
		$this->source->touch('foo/file4');

		$this->aclPermissions['foo/file2'] = Constants::PERMISSION_READ;
		$this->aclPermissions['foo/file3'] = Constants::PERMISSION_READ + Constants::PERMISSION_UPDATE;
		$this->aclPermissions['foo/file4'] = 0;

		$dh = $this->storage->opendir('foo');
		$result = [];
		while ($file = readdir($dh)) {
			$result[] = $file;
		}

		$expected = ['file1', 'file2', 'file3'];
		sort($result);
		sort($expected);
		$this->assertEquals($expected, $result);
	}

	public function testMove() {
		$this->source->mkdir('foo');
		$this->source->touch('file1');

		$this->aclPermissions[''] = Constants::PERMISSION_READ;
		$this->aclPermissions['foo'] = Constants::PERMISSION_ALL;

		$this->assertFalse($this->storage->rename('file1', 'foo/file1'));

		$this->aclPermissions[''] = Constants::PERMISSION_READ + Constants::PERMISSION_DELETE;

		$this->assertTrue($this->storage->rename('file1', 'foo/file1'));
	}
}
