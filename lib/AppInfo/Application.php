<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\AppInfo;

use OC\Group\Manager;
use OCA\GroupFolders\Command\ExpireGroupVersions;
use OCA\GroupFolders\Command\ExpireGroupVersionsPlaceholder;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Trash\TrashManager;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\NotFoundException;
use OCP\IGroup;
use OCP\IGroupManager;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('groupfolders', $urlParams);

		$container = $this->getContainer();

		$container->registerService('GroupAppFolder', function(IAppContainer $c) {
			try {
				return $c->getServer()->getRootFolder()->get('__groupfolders');
			} catch (NotFoundException $e) {
				return $c->getServer()->getRootFolder()->newFolder('__groupfolders');
			}
		});

		$container->registerService(MountProvider::class, function (IAppContainer $c) {
			$rootProvider = function () use ($c) {
				return $c->query('GroupAppFolder');
			};

			return new MountProvider(
				$c->getServer()->getGroupManager(),
				$c->query(FolderManager::class),
				$rootProvider
			);
		});

		$container->registerService(TrashBackend::class, function(IAppContainer $c) {
			return new TrashBackend(
				$c->query(FolderManager::class),
				$c->query(TrashManager::class),
				$c->query('GroupAppFolder'),
				$c->query(MountProvider::class)
			);
		});

		$container->registerService(VersionsBackend::class, function(IAppContainer $c) {
			return new VersionsBackend(
				$c->query('GroupAppFolder'),
				$c->query(MountProvider::class),
				$c->query(ITimeFactory::class)
			);
		});

		$container->registerService(ExpireGroupVersions::class, function(IAppContainer $c) {
			if (interface_exists('OCA\Files_Versions\Versions\IVersionBackend')) {
				return new ExpireGroupVersions(
					$c->query(GroupVersionsExpireManager::class)
				);
			} else {
				return new ExpireGroupVersionsPlaceholder();
			}
		});

		$container->registerService(\OCA\GroupFolders\BackgroundJob\ExpireGroupVersions::class, function(IAppContainer $c) {
			if (interface_exists('OCA\Files_Versions\Versions\IVersionBackend')) {
				return new \OCA\GroupFolders\BackgroundJob\ExpireGroupVersions(
					$c->query(GroupVersionsExpireManager::class)
				);
			} else {
				return new \OCA\GroupFolders\BackgroundJob\ExpireGroupVersionsPlaceholder();
			}
		});
	}

	public function register() {
		$container = $this->getContainer();

		$container->getServer()->getMountProviderCollection()->registerProvider($this->getMountProvider());

		/** @var IGroupManager|Manager $groupManager */
		$groupManager = $this->getContainer()->getServer()->getGroupManager();
		$groupManager->listen('\OC\Group', 'postDelete', function(IGroup $group) {
			$this->getFolderManager()->deleteGroup($group->getGID());
		});
	}

	/**
	 * @return MountProvider
	 */
	public function getMountProvider() {
		return $this->getContainer()->query(MountProvider::class);
	}

	/**
	 * @return FolderManager
	 */
	public function getFolderManager() {
		return $this->getContainer()->query(FolderManager::class);
	}
}
