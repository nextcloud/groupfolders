<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Folder;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\SubfolderManagerService;
use OCA\GroupFolders\Folder\SubfolderQuotaManager;
use OCP\IUser;
use OCP\Server;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class SubfolderManagerServiceTest extends TestCase {
	use UserTrait;

	private FolderManager $folderManager;
	private SubfolderQuotaManager $subfolderQuotaManager;
	private SubfolderManagerService $subfolderManagerService;
	private int $folderId;
	private IUser $managerUser;
	private IUser $otherUser;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->folderManager = Server::get(FolderManager::class);
		$this->subfolderQuotaManager = Server::get(SubfolderQuotaManager::class);
		$this->subfolderManagerService = Server::get(SubfolderManagerService::class);
		$this->folderId = $this->folderManager->createFolder('subfolder-manager-' . bin2hex(random_bytes(8)));
		$this->folderManager->setFolderACL($this->folderId, true);
		$this->managerUser = $this->createUser('subfolder-manager-' . bin2hex(random_bytes(4)), 'test');
		$this->otherUser = $this->createUser('subfolder-other-' . bin2hex(random_bytes(4)), 'test');
	}

	#[\Override]
	protected function tearDown(): void {
		if ($this->folderManager->getFolder($this->folderId) !== null) {
			$this->folderManager->removeFolder($this->folderId);
		}

		parent::tearDown();
	}

	public function testDelegateOnlyManagesItsAssignedSubfolder(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);
		$first = $this->subfolderQuotaManager->createSubfolder($folder, 'first', 1024);
		$this->subfolderQuotaManager->createSubfolder($folder, 'second', 1024);

		$this->subfolderManagerService->setManager($folder, $first->fileId, 'user', $this->managerUser->getUID(), true);

		$managers = $this->subfolderManagerService->getManagers($folder, $first->fileId);
		self::assertCount(1, $managers);
		self::assertSame($this->managerUser->getUID(), $managers[0]->getId());
		self::assertTrue($this->subfolderManagerService->canManageSubfolder($folder, $first->fileId, $this->managerUser));
		self::assertTrue($this->subfolderManagerService->canManagePath($folder, 'first/nested/file.txt', $this->managerUser));
		self::assertFalse($this->subfolderManagerService->canManagePath($folder, 'second/file.txt', $this->managerUser));
		self::assertFalse($this->subfolderManagerService->canManageSubfolder($folder, $first->fileId, $this->otherUser));

		$this->subfolderManagerService->setManager($folder, $first->fileId, 'user', $this->managerUser->getUID(), false);
		self::assertFalse($this->subfolderManagerService->canManageSubfolder($folder, $first->fileId, $this->managerUser));
	}

	public function testManagerMustBeAssignedToADirectChild(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);

		$this->expectException(\InvalidArgumentException::class);
		$this->subfolderManagerService->setManager($folder, $folder->rootId, 'user', $this->managerUser->getUID(), true);
	}

	public function testDisablingAdvancedPermissionsSuspendsSubfolderManagers(): void {
		$folder = $this->folderManager->getFolder($this->folderId);
		self::assertNotNull($folder);
		$subfolder = $this->subfolderQuotaManager->createSubfolder($folder, 'first', 1024);
		$this->subfolderManagerService->setManager($folder, $subfolder->fileId, 'user', $this->managerUser->getUID(), true);

		$this->folderManager->setFolderACL($folder->id, false);
		$inactiveFolder = $this->folderManager->getFolder($folder->id);
		self::assertNotNull($inactiveFolder);
		self::assertFalse($this->subfolderManagerService->canManageSubfolder($inactiveFolder, $subfolder->fileId, $this->managerUser));

		$this->folderManager->setFolderACL($folder->id, true);
		$reactivatedFolder = $this->folderManager->getFolder($folder->id);
		self::assertNotNull($reactivatedFolder);

		self::assertTrue($this->subfolderManagerService->canManageSubfolder($reactivatedFolder, $subfolder->fileId, $this->managerUser));
	}
}
