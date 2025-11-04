<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Storage\Temporary;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLStorageWrapper;
use OCP\Constants;
use OCP\Files\Storage\IStorage;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ACLStorageWrapperTest extends TestCase {
	private ACLManager&MockObject $aclManager;
	private IStorage $source;
	private ACLStorageWrapper $storage;
	private array $aclPermissions = [];

	protected function setUp(): void {
		parent::setUp();

		$this->aclManager = $this->createMock(ACLManager::class);
		$this->aclManager->method('getACLPermissionsForPath')
			->willReturnCallback(fn (int $folderId, int $storageId, string $path) => $this->aclPermissions[$path] ?? Constants::PERMISSION_ALL);
		$this->source = new Temporary([]);
		$this->storage = new ACLStorageWrapper([
			'storage' => $this->source,
			'acl_manager' => $this->aclManager,
			'in_share' => false,
			'folder_id' => 0,
			'storage_id' => $this->source->getCache()->getNumericStorageId(),
		]);
	}

	public function testNoReadImpliesNothing(): void {
		$this->source->mkdir('foo');
		$this->aclPermissions['foo'] = Constants::PERMISSION_ALL - Constants::PERMISSION_READ;

		$this->assertEquals(false, $this->storage->isUpdatable('foo'));
		$this->assertEquals(false, $this->storage->isCreatable('foo'));
		$this->assertEquals(false, $this->storage->isDeletable('foo'));
		$this->assertEquals(false, $this->storage->isSharable('foo'));
	}

	public function testOpenDir(): void {
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
		/** @psalm-suppress PossiblyFalseArgument */
		while ($file = readdir($dh)) {
			$result[] = $file;
		}

		$expected = ['file1', 'file2', 'file3'];
		sort($result);
		sort($expected);
		$this->assertEquals($expected, $result);
	}

	public function testMove(): void {
		$this->source->mkdir('foo');
		$this->source->touch('file1');

		$this->aclPermissions[''] = Constants::PERMISSION_READ;
		$this->aclPermissions['foo'] = Constants::PERMISSION_ALL;

		$this->assertFalse($this->storage->rename('file1', 'foo/file1'));

		$this->aclPermissions[''] = Constants::PERMISSION_READ + Constants::PERMISSION_DELETE;

		$this->assertTrue($this->storage->rename('file1', 'foo/file1'));
	}
}
