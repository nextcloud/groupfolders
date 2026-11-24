<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Mount;

use OC\Files\Filesystem;
use OC\Files\SetupManager;
use OC\Group\Database;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Server;
use Test\TestCase;
use Test\Traits\UserTrait;

/**
 * Regression test for a group folder mounted inside another group folder
 * ("nested group folders") reporting an inflated used-space, growing on every
 * read instead of matching the nested folder's real size.
 *
 * @group DB
 */
class NestedGroupFolderSizeTest extends TestCase {
	use UserTrait;

	private const OUTER_NAME = 'nested_size_outer';
	private const INNER_NAME = 'nested_size_outer/nested_size_inner';
	private const FILE_SIZE = 2048;

	private FolderManager $folderManager;
	private int $outerId;
	private int $innerId;
	private Folder $userFolder;

	public function setUp(): void {
		parent::setUp();

		$this->createUser('nested_size_user', 'test');

		/** @var Database $groupBackend */
		$groupBackend = Server::get(Database::class);
		$groupBackend->createGroup('nested_size_group');
		$groupBackend->addToGroup('nested_size_user', 'nested_size_group');

		$this->folderManager = Server::get(FolderManager::class);
		$this->outerId = $this->folderManager->createFolder(self::OUTER_NAME);
		$this->innerId = $this->folderManager->createFolder(self::INNER_NAME);
		$this->folderManager->addApplicableGroup($this->outerId, 'nested_size_group');
		$this->folderManager->addApplicableGroup($this->innerId, 'nested_size_group');

		$this->loginAsUser('nested_size_user');

		/** @var IRootFolder $rootFolder */
		$rootFolder = Server::get(IRootFolder::class);
		$this->userFolder = $rootFolder->getUserFolder('nested_size_user');

		$this->userFolder->get(self::INNER_NAME)->newFile('test.txt', str_repeat('a', self::FILE_SIZE));
	}

	protected function tearDown(): void {
		$this->folderManager->removeFolder($this->innerId);
		$this->folderManager->removeFolder($this->outerId);

		/** @var SetupManager $setupManager */
		$setupManager = Server::get(SetupManager::class);
		$setupManager->tearDown();
		parent::tearDown();
	}

	/**
	 * The outer folder's size (which folds in the nested folder's size, the
	 * same way it would fold in an external storage or share mounted inside
	 * it) must reflect the nested folder's actual content, and must not grow
	 * with every subsequent read.
	 */
	public function testNestedFolderSizeIsNotInflatedOnRepeatedReads(): void {
		for ($i = 1; $i <= 3; $i++) {
			$info = Filesystem::getView()->getFileInfo('/' . self::OUTER_NAME);
			$this->assertSame(self::FILE_SIZE, $info->getSize(true), "outer folder size incorrect on read #$i");
		}
	}
}
