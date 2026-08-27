<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Folder;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\SubfolderQuotaManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCP\Files\FileInfo;
use OCP\IUserSession;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class SubfolderQuotaManagerTest extends TestCase {
	private FolderManager $folderManager;
	private SubfolderQuotaManager $subfolderQuotaManager;
	private int $folderId;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->folderManager = Server::get(FolderManager::class);
		$this->subfolderQuotaManager = Server::get(SubfolderQuotaManager::class);
		$this->folderId = $this->folderManager->createFolder('subfolder-quota-' . bin2hex(random_bytes(8)));
	}

	#[\Override]
	protected function tearDown(): void {
		if ($this->folderManager->getFolder($this->folderId) !== null) {
			$this->folderManager->removeFolder($this->folderId);
		}

		parent::tearDown();
	}

	public function testCreateSubfolderWithQuota(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);

		$created = $this->subfolderQuotaManager->createSubfolder($folder, 'first', 1024);

		self::assertSame('first', $created->name);
		self::assertSame(1024, $created->quota);
		self::assertGreaterThan(0, $created->fileId);

		$subfolders = $this->subfolderQuotaManager->getSubfolderQuotas($folder);
		self::assertCount(1, $subfolders);
		self::assertSame($created->fileId, $subfolders[0]->fileId);
		self::assertSame(1024, $subfolders[0]->quota);
	}

	public function testUnlimitedRemovesIndependentQuota(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);

		$created = $this->subfolderQuotaManager->createSubfolder($folder, 'first', 1024);
		$updated = $this->subfolderQuotaManager->setSubfolderQuota(
			$folder,
			$created->fileId,
			FileInfo::SPACE_UNLIMITED,
		);

		self::assertNull($updated->quota);
		$subfolders = $this->subfolderQuotaManager->getSubfolderQuotas($folder);
		self::assertCount(1, $subfolders);
		self::assertNull($subfolders[0]->quota);
	}

	public function testQuotaCanOnlyBeSetOnDirectChildFolders(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);

		$this->expectException(\InvalidArgumentException::class);
		$this->subfolderQuotaManager->setSubfolderQuota($folder, $folder->rootId, 1024);
	}

	public function testSubfolderQuotaCannotExceedTeamFolderQuota(): void {
		$this->folderManager->setFolderQuota($this->folderId, 1024);
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);

		$this->expectException(\InvalidArgumentException::class);
		$this->subfolderQuotaManager->createSubfolder($folder, 'too-large', 1025);
	}

	public function testTeamFolderQuotaCannotBeLoweredBelowConfiguredSubfolderQuota(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);
		$this->subfolderQuotaManager->createSubfolder($folder, 'limited', 1024);

		$this->expectException(\InvalidArgumentException::class);
		$this->folderManager->setFolderQuota($this->folderId, 1023);
	}

	public function testSubfolderQuotaCapsAreNotReservedAllocations(): void {
		$this->folderManager->setFolderQuota($this->folderId, 10 * 1024);
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);

		$first = $this->subfolderQuotaManager->createSubfolder($folder, 'first', 8 * 1024);
		$second = $this->subfolderQuotaManager->createSubfolder($folder, 'second', 8 * 1024);

		self::assertSame(8 * 1024, $first->quota);
		self::assertSame(8 * 1024, $second->quota);
	}

	public function testSubfoldersContinueToShareTheTeamFolderQuota(): void {
		$this->folderManager->setFolderQuota($this->folderId, 10);
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);
		$this->subfolderQuotaManager->createSubfolder($folder, 'first', 8);
		$this->subfolderQuotaManager->createSubfolder($folder, 'second', 8);

		/** @var FolderStorageManager $folderStorageManager */
		$folderStorageManager = Server::get(FolderStorageManager::class);
		/** @var IUserSession $userSession */
		$userSession = Server::get(IUserSession::class);
		$storage = new GroupFolderStorage([
			'storage' => $folderStorageManager->getBaseStorageForFolder(
				$folder->id,
				$folder->useSeparateStorage(),
				$folder,
			),
			'quota' => $folder->quota,
			'folder' => $folder,
			'rootCacheEntry' => $folder->rootCacheEntry,
			'userSession' => $userSession,
			'mountOwner' => null,
			'subfolderQuotaManager' => $this->subfolderQuotaManager,
			'applySubfolderQuotas' => true,
		]);

		self::assertSame(6, $storage->file_put_contents('first/first.txt', '123456'));
		self::assertSame(4, $storage->free_space('second/second.txt'));
		self::assertFalse($storage->file_put_contents('second/second.txt', '12345'));
	}

	public function testSubfolderQuotaIsAppliedWhenTeamFolderIsUnlimited(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);
		$this->subfolderQuotaManager->createSubfolder($folder, 'limited', 10);

		/** @var FolderStorageManager $folderStorageManager */
		$folderStorageManager = Server::get(FolderStorageManager::class);
		/** @var IUserSession $userSession */
		$userSession = Server::get(IUserSession::class);
		$storage = new GroupFolderStorage([
			'storage' => $folderStorageManager->getBaseStorageForFolder(
				$folder->id,
				$folder->useSeparateStorage(),
				$folder,
			),
			'quota' => FileInfo::SPACE_UNLIMITED,
			'folder' => $folder,
			'rootCacheEntry' => $folder->rootCacheEntry,
			'userSession' => $userSession,
			'mountOwner' => null,
			'subfolderQuotaManager' => $this->subfolderQuotaManager,
			'applySubfolderQuotas' => true,
		]);

		self::assertSame(3, $storage->file_put_contents('limited/first.txt', 'abc'));
		self::assertSame(7, $storage->free_space('limited/second.txt'));
		self::assertFalse($storage->file_put_contents('limited/second.txt', '12345678'));
		self::assertFalse($storage->rename('limited', 'nested/limited'));
	}
}
