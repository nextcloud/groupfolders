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
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupFolderStorage;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\Server;
use OCP\Share;
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
		$groupBackend = \OCP\Server::get(Database::class);
		$groupBackend->createGroup('gf_manager');
		$groupBackend->createGroup('gf_normal');
		$groupBackend->addToGroup('manager', 'gf_manager');
		$groupBackend->addToGroup('normal', 'gf_normal');

		$this->trashBackend = \OCP\Server::get(TrashBackend::class);
		$this->folderManager = \OCP\Server::get(FolderManager::class);
		/** @var ACLManagerFactory $aclManagerFactory */
		$aclManagerFactory = \OCP\Server::get(ACLManagerFactory::class);
		$this->aclManager = $aclManagerFactory->getACLManager($this->managerUser);
		$this->ruleManager = \OCP\Server::get(RuleManager::class);

		$this->folderId = $this->folderManager->createFolder($this->folderName);
		$this->folderManager->addApplicableGroup($this->folderId, 'gf_manager');
		$this->folderManager->addApplicableGroup($this->folderId, 'gf_normal');
		$this->folderManager->setFolderACL($this->folderId, true);
		$this->folderManager->setManageACL($this->folderId, 'user', 'manager', true);

		/** @var IRootFolder $rootFolder */
		$rootFolder = \OCP\Server::get(IRootFolder::class);

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
		$folder = $this->folderManager->getFolder($this->folderId);
		if ($folder) {
			$folderWithPermissions = FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL);
			$this->trashBackend->cleanTrashFolder($folderWithPermissions);
			$this->folderManager->removeFolder($this->folderId);
		}

		/** @var SetupManager $setupManager */
		$setupManager = \OCP\Server::get(SetupManager::class);
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

	public function testHideTrashItemAcl(): void {
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

	public function testHideItemInDeletedFolderAcl(): void {
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
		$this->assertNotFalse($managerTrashFolder);
		$this->assertCount(1, $this->trashBackend->listTrashRoot($this->normalUser));
		$normalTrashFolder = current($this->trashBackend->listTrashRoot($this->normalUser));
		$this->assertNotFalse($normalTrashFolder);

		// only the manager can see the restricted child, both can see the un-restricted child
		$this->assertCount(2, $this->trashBackend->listTrashFolder($managerTrashFolder));
		$this->assertCount(1, $this->trashBackend->listTrashFolder($normalTrashFolder));

		$this->logout();
	}

	public function testHideDeletedTrashItemInDeletedFolderAcl(): void {
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

	public function testHideDeletedTrashItemInDeletedParentFolderAcl(): void {
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

	public function testWrongOriginalLocation(): void {
		$shareManager = Server::get(Share\IManager::class);

		$userA = $this->createUser('A', 'test');
		$userAFolder = Server::get(IRootFolder::class)->getUserFolder('A');

		$userB = $this->createUser('B', 'test');
		$userBFolder = Server::get(IRootFolder::class)->getUserFolder('B');

		$groupBackend = Server::get(Database::class);
		$groupBackend->createGroup('C');
		$groupBackend->addToGroup('A', 'C');
		$groupBackend->addToGroup('B', 'C');
		$this->assertCount(2, $groupBackend->usersInGroup('C'));

		$groupFolderId = $this->folderManager->createFolder('D');
		$this->folderManager->setFolderACL($groupFolderId, true);
		$this->folderManager->addApplicableGroup($groupFolderId, 'C');
		$this->folderManager->setGroupPermissions($groupFolderId, 'C', Constants::PERMISSION_ALL);
		$this->assertInstanceOf(Folder::class, $userAFolder->get('D'));
		$this->assertInstanceOf(Folder::class, $userBFolder->get('D'));

		$this->loginAsUser('A');
		$userAFolder->newFolder('D/E/F');
		$userAFolder->newFile('D/E/F/G', 'foo');

		$this->ruleManager->saveRule(new Rule(new UserMapping('group', 'C'), $userAFolder->get('D/E')->getId(), Constants::PERMISSION_READ, 0));
		$this->ruleManager->saveRule(new Rule(new UserMapping('user', 'A'), $userAFolder->get('D/E')->getId(), Constants::PERMISSION_ALL, Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE));
		$this->ruleManager->saveRule(new Rule(new UserMapping('user', 'A'), $userAFolder->get('D/E/F')->getId(), Constants::PERMISSION_ALL, Constants::PERMISSION_ALL));

		$folderShare = $shareManager->newShare();
		$folderShare->setShareType(Share\IShare::TYPE_USER);
		$folderShare->setSharedWith('B');
		$folderShare->setSharedBy('A');
		$folderShare->setPermissions(Constants::PERMISSION_ALL);
		$folderShare->setNode($userAFolder->get('D/E/F'));
		$folderShare = $shareManager->createShare($folderShare);
		$this->assertNotEmpty($folderShare->getId());

		$fileShare = $shareManager->newShare();
		$fileShare->setShareType(Share\IShare::TYPE_USER);
		$fileShare->setSharedWith('B');
		$fileShare->setSharedBy('A');
		$fileShare->setPermissions(19);
		$fileShare->setNode($userAFolder->get('D/E/F/G'));
		$fileShare = $shareManager->createShare($fileShare);
		$this->assertNotEmpty($fileShare->getId());

		$this->loginAsUser('B');
		$this->assertTrue($userBFolder->get('F/G')->isDeletable());
		$userBFolder->get('F/G')->delete();

		$this->assertFalse($userAFolder->nodeExists('D/E/F/G'));

		$trashedOfUserA = $this->trashBackend->listTrashRoot($userA);
		$this->assertCount(1, $trashedOfUserA);
		// Ensure original location inside share is correct
		$this->assertSame('D/E/F/G', $trashedOfUserA[0]->getOriginalLocation());

		$trashedOfUserB = $this->trashBackend->listTrashRoot($userB);
		// Deleting share only unshares it, so no trash here
		$this->assertCount(0, $trashedOfUserB);

		// Restoring to original location works
		$this->trashBackend->restoreItem($trashedOfUserA[0]);
		$this->assertTrue($userAFolder->nodeExists('D/E/F/G'));
	}
}
