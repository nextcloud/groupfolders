<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Trash;

use OC\Files\SetupManager;
use OC\Group\Database;
use OCA\GroupFolders\ACL\ACLManager;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * @group DB
 */
class TrashBackendTest extends TestCase {
	use UserTrait;

	private string $folderName;
	private TrashBackend $trashBackend;
	private FolderManager $folderManager;
	private ACLManager $aclManager;
	private RuleManager $ruleManager;
	private int $folderId;
	private Folder $managerUserFolder;
	private Folder $normalUserFolder;
	private IUser $managerUser;
	private IUser $normalUser;

	public function setUp(): void {
		parent::setUp();

		$this->folderName = 'gf';
		$this->managerUser = $this->createUser('manager', 'test');
		$this->normalUser = $this->createUser('normal', 'test');

		/** @var Database $groupBackend */
		$groupBackend = \OC::$server->get(Database::class);
		$groupBackend->createGroup('gf_manager');
		$groupBackend->createGroup('gf_normal');
		$groupBackend->addToGroup('manager', 'gf_manager');
		$groupBackend->addToGroup('normal', 'gf_normal');

		$this->trashBackend = \OC::$server->get(TrashBackend::class);
		$this->folderManager = \OC::$server->get(FolderManager::class);
		/** @var ACLManagerFactory $aclManagerFactory */
		$aclManagerFactory = \OC::$server->get(ACLManagerFactory::class);
		$this->aclManager = $aclManagerFactory->getACLManager($this->managerUser);
		$this->ruleManager = \OC::$server->get(RuleManager::class);

		$this->folderId = $this->folderManager->createFolder($this->folderName);
		$this->folderManager->addApplicableGroup($this->folderId, 'gf_manager');
		$this->folderManager->addApplicableGroup($this->folderId, 'gf_normal');
		$this->folderManager->setFolderACL($this->folderId, true);
		$this->folderManager->setManageACL($this->folderId, 'user', 'manager', true);

		/** @var IRootFolder $rootFolder */
		$rootFolder = \OC::$server->get(IRootFolder::class);

		$this->managerUserFolder = $rootFolder->getUserFolder('manager');
		$this->normalUserFolder = $rootFolder->getUserFolder('normal');

		$this->assertTrue($this->managerUserFolder->nodeExists($this->folderName));
		$this->assertTrue($this->normalUserFolder->nodeExists($this->folderName));

		/** @var GroupFolderStorage $groupFolderStorage */
		$groupFolderStorage = $this->managerUserFolder->get($this->folderName)->getStorage();
		$this->assertTrue($groupFolderStorage->instanceOfStorage(GroupFolderStorage::class));
		$this->assertEquals($this->folderId, $groupFolderStorage->getFolderId());

	}

	protected function tearDown(): void {
		$this->trashBackend->cleanTrashFolder($this->folderId);
		$this->folderManager->removeFolder($this->folderId);

		/** @var SetupManager $setupManager */
		$setupManager = \OC::$server->get(SetupManager::class);
		$setupManager->tearDown();
		parent::tearDown();
	}


	private function createNoReadRule(string $userId, int $fileId): Rule {
		return new Rule(
			new UserMapping('user', $userId),
			$fileId,
			1,
			0,
		);
	}

	public function testHideTrashItemAcl() {
		$this->loginAsUser('manager');

		$restricted = $this->managerUserFolder->newFile("{$this->folderName}/restricted.txt", 'content');
		$this->ruleManager->saveRule($this->createNoReadRule('normal', $restricted->getId()));

		$this->assertTrue($this->managerUserFolder->nodeExists("{$this->folderName}/restricted.txt"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/restricted.txt"));

		$this->trashBackend->moveToTrash($restricted->getStorage(), $restricted->getInternalPath());

		$this->assertFalse($this->managerUserFolder->nodeExists("{$this->folderName}/restricted.txt"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/restricted.txt"));

		// only the manager can see the deleted file
		$this->assertCount(1, $this->trashBackend->listTrashRoot($this->managerUser));
		$this->assertCount(0, $this->trashBackend->listTrashRoot($this->normalUser));

		$this->logout();
	}

	public function testHideItemInDeletedFolderAcl() {
		$this->loginAsUser('manager');

		$folder = $this->managerUserFolder->newFolder("{$this->folderName}/folder");
		$folder->newFile('file.txt', 'content1');
		$restrictedChild = $folder->newFile('restricted.txt', 'content2');
		$this->ruleManager->saveRule($this->createNoReadRule('normal', $restrictedChild->getId()));

		$this->assertTrue($this->managerUserFolder->nodeExists("{$this->folderName}/folder/restricted.txt"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/folder/restricted.txt"));

		$this->trashBackend->moveToTrash($folder->getStorage(), $folder->getInternalPath());

		$this->assertFalse($this->managerUserFolder->nodeExists("{$this->folderName}/folder"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/folder"));

		// everyone can see the parent folder
		$this->assertCount(1, $this->trashBackend->listTrashRoot($this->managerUser));
		$managerTrashFolder = current($this->trashBackend->listTrashRoot($this->managerUser));
		$this->assertCount(1, $this->trashBackend->listTrashRoot($this->normalUser));
		$normalTrashFolder = current($this->trashBackend->listTrashRoot($this->normalUser));

		// only the manager can see the restricted child, both can see the un-restricted child
		$this->assertCount(2, $this->trashBackend->listTrashFolder($managerTrashFolder));
		$this->assertCount(1, $this->trashBackend->listTrashFolder($normalTrashFolder));

		$this->logout();
	}

	public function testHideDeletedTrashItemInDeletedFolderAcl() {
		$this->loginAsUser('manager');

		$folder = $this->managerUserFolder->newFolder("{$this->folderName}/restricted");
		$child = $folder->newFile('file.txt', 'content1');
		$this->ruleManager->saveRule($this->createNoReadRule('normal', $folder->getId()));

		$this->assertTrue($this->managerUserFolder->nodeExists("{$this->folderName}/restricted/file.txt"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/restricted/file.txt"));

		$this->trashBackend->moveToTrash($child->getStorage(), $child->getInternalPath());

		$this->assertFalse($this->managerUserFolder->nodeExists("{$this->folderName}/restricted/file.txt"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/restricted/file.txt"));

		// only the manager can see the deleted child
		$this->assertCount(1, $this->trashBackend->listTrashRoot($this->managerUser));
		$this->assertCount(0, $this->trashBackend->listTrashRoot($this->normalUser));

		$this->trashBackend->moveToTrash($folder->getStorage(), $folder->getInternalPath());

		// only the manager can see the deleted items
		$this->assertCount(2, $this->trashBackend->listTrashRoot($this->managerUser));
		$this->assertCount(0, $this->trashBackend->listTrashRoot($this->normalUser));

		$this->logout();
	}

	public function testHideDeletedTrashItemInDeletedParentFolderAcl() {
		$this->loginAsUser('manager');

		$parent = $this->managerUserFolder->newFolder("{$this->folderName}/parent");
		$folder = $parent->newFolder('restricted');
		$child = $folder->newFile('file.txt', 'content1');
		$this->ruleManager->saveRule($this->createNoReadRule('normal', $folder->getId()));

		$this->assertTrue($this->managerUserFolder->nodeExists("{$this->folderName}/parent/restricted/file.txt"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/parent/restricted/file.txt"));

		$this->trashBackend->moveToTrash($child->getStorage(), $child->getInternalPath());

		$this->assertFalse($this->managerUserFolder->nodeExists("{$this->folderName}/restricted/file.txt"));
		$this->assertFalse($this->normalUserFolder->nodeExists("{$this->folderName}/restricted/file.txt"));

		// only the manager can see the deleted child
		$this->assertCount(1, $this->trashBackend->listTrashRoot($this->managerUser));
		$this->assertCount(0, $this->trashBackend->listTrashRoot($this->normalUser));

		$this->trashBackend->moveToTrash($parent->getStorage(), $parent->getInternalPath());

		// only the manager can see the deleted child, both can see the deleted parent
		$this->assertCount(2, $this->trashBackend->listTrashRoot($this->managerUser));
		$this->assertCount(1, $this->trashBackend->listTrashRoot($this->normalUser));

		$this->logout();
	}
}
