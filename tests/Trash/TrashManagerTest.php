<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Trash;

use OCA\GroupFolders\Trash\TrashManager;
use OCP\IDBConnection;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class TrashManagerTest extends TestCase {
	private TrashManager $trashManager;
	private IDBConnection $connection;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->connection = Server::get(IDBConnection::class);
		$this->trashManager = new TrashManager($this->connection);
		$this->cleanTrash();
	}

	#[\Override]
	protected function tearDown(): void {
		$this->cleanTrash();
		parent::tearDown();
	}

	private function cleanTrash(): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_trash')->executeStatement();
	}

	public function testListTrashForFolders(): void {
		$this->trashManager->addTrashItem(1, 'file1.txt', 1000, 'path/to/file1.txt', 101, 'user1');
		$this->trashManager->addTrashItem(2, 'file2.txt', 2000, 'path/to/file2.txt', 102, 'user2');
		$this->trashManager->addTrashItem(3, 'file3.txt', 3000, 'path/to/file3.txt', 103, 'user3');

		$result = $this->trashManager->listTrashForFolders([1, 2]);

		$this->assertCount(2, $result);
		$folderIds = array_column($result, 'folder_id');
		$this->assertContains(1, $folderIds);
		$this->assertContains(2, $folderIds);
		$this->assertNotContains(3, $folderIds);
	}

	public function testListTrashForFoldersChunked(): void {
		$this->trashManager->addTrashItem(1, 'file.txt', 1000, 'path/to/file.txt', 101, 'user1');

		// Build a list of 1001 folder IDs; the folder with the trash item is placed
		// in the second chunk to exercise the chunking code path.
		$folderIds = range(2, 1001);
		$folderIds[] = 1;

		$result = $this->trashManager->listTrashForFolders($folderIds);

		$this->assertCount(1, $result);
		$this->assertEquals(1, $result[0]['folder_id']);
		$this->assertEquals('file.txt', $result[0]['name']);
	}
}
