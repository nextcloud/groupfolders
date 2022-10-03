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
