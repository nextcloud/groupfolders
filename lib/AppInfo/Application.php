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
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\UserMapping\UserMappingManager;
use OCA\GroupFolders\CacheListener;
use OCA\GroupFolders\Command\ExpireGroupVersions;
use OCA\GroupFolders\Command\ExpireGroupVersionsPlaceholder;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Helper\LazyFolder;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Trash\TrashManager;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IMountProviderCollection;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('groupfolders', $urlParams);

		$container = $this->getContainer();

		$container->registerAlias('GroupAppFolder', LazyFolder::class);

		$container->registerService(MountProvider::class, function (IAppContainer $c) {
			$rootProvider = function () use ($c) {
				return $c->query('GroupAppFolder');
			};

			return new MountProvider(
				$c->getServer()->getGroupManager(),
				$c->query(FolderManager::class),
				$rootProvider,
				$c->query(ACLManagerFactory::class),
				$c->query(IUserSession::class),
				$c->query(IRequest::class),
				$c->query(ISession::class),
				$c->query(IMountProviderCollection::class),
				$c->query(IDBConnection::class)
			);
		});

		$container->registerService(TrashBackend::class, function (IAppContainer $c) {
			return new TrashBackend(
				$c->query(FolderManager::class),
				$c->query(TrashManager::class),
				$c->query('GroupAppFolder'),
				$c->query(MountProvider::class),
				$c->query(ACLManagerFactory::class)
			);
		});

		$container->registerService(VersionsBackend::class, function (IAppContainer $c) {
			return new VersionsBackend(
				$c->query('GroupAppFolder'),
				$c->query(MountProvider::class),
				$c->query(ITimeFactory::class)
			);
		});

		$container->registerService(ExpireGroupVersions::class, function (IAppContainer $c) {
			if (interface_exists('OCA\Files_Versions\Versions\IVersionBackend')) {
				return new ExpireGroupVersions(
					$c->query(GroupVersionsExpireManager::class)
				);
			} else {
				return new ExpireGroupVersionsPlaceholder();
			}
		});

		$container->registerService(\OCA\GroupFolders\BackgroundJob\ExpireGroupVersions::class, function (IAppContainer $c) {
			if (interface_exists('OCA\Files_Versions\Versions\IVersionBackend')) {
				return new \OCA\GroupFolders\BackgroundJob\ExpireGroupVersions(
					$c->query(GroupVersionsExpireManager::class)
				);
			} else {
				return new \OCA\GroupFolders\BackgroundJob\ExpireGroupVersionsPlaceholder();
			}
		});

		$container->registerService(ACLManagerFactory::class, function (IAppContainer $c) {
			$rootFolderProvider = function () use ($c) {
				return $c->getServer()->getRootFolder();
			};
			return new ACLManagerFactory(
				$c->query(RuleManager::class),
				$rootFolderProvider
			);
		});

		$container->registerAlias(IUserMappingManager::class, UserMappingManager::class);
	}

	public function register() {
		$container = $this->getContainer();

		$container->getServer()->getMountProviderCollection()->registerProvider($this->getMountProvider());

		/** @var IGroupManager|Manager $groupManager */
		$groupManager = $this->getContainer()->getServer()->getGroupManager();
		$groupManager->listen('\OC\Group', 'postDelete', function (IGroup $group) {
			$this->getFolderManager()->deleteGroup($group->getGID());
		});

		/** @var CacheListener $cacheListener */
		$cacheListener = $container->query(CacheListener::class);
		$cacheListener->listen();
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
