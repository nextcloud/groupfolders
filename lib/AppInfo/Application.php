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

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\UserMapping\UserMappingManager;
use OCA\GroupFolders\CacheListener;
use OCA\GroupFolders\Command\ExpireGroupVersions;
use OCA\GroupFolders\Command\ExpireGroupVersionsPlaceholder;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Helper\LazyFolder;
use OCA\GroupFolders\Listeners\LoadAdditionalScriptsListener;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Trash\TrashManager;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Config\IMountProviderCollection;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;

class Application extends App implements IBootstrap {
	public function __construct(array $urlParams = []) {
		parent::__construct('groupfolders', $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, LoadAdditionalScriptsListener::class);

		$context->registerServiceAlias('GroupAppFolder', LazyFolder::class);

		$context->registerService(MountProvider::class, function (IAppContainer $c) {
			$rootProvider = function () use ($c) {
				return $c->get('GroupAppFolder');
			};

			return new MountProvider(
				$c->getServer()->getGroupManager(),
				$c->get(FolderManager::class),
				$rootProvider,
				$c->get(ACLManagerFactory::class),
				$c->get(IUserSession::class),
				$c->get(IRequest::class),
				$c->get(ISession::class),
				$c->get(IMountProviderCollection::class),
				$c->get(IDBConnection::class)
			);
		});

		$context->registerService(TrashBackend::class, function (IAppContainer $c) {
			return new TrashBackend(
				$c->get(FolderManager::class),
				$c->get(TrashManager::class),
				$c->get('GroupAppFolder'),
				$c->get(MountProvider::class),
				$c->get(ACLManagerFactory::class),
				$c->getServer()->getRootFolder(),
				$c->get(VersionsBackend::class)
			);
		});

		$context->registerService(VersionsBackend::class, function (IAppContainer $c) {
			return new VersionsBackend(
				$c->get('GroupAppFolder'),
				$c->get(MountProvider::class),
				$c->get(ITimeFactory::class)
			);
		});

		$context->registerService(ExpireGroupVersions::class, function (IAppContainer $c) {
			if (interface_exists('OCA\Files_Versions\Versions\IVersionBackend')) {
				return new ExpireGroupVersions(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(TrashBackend::class),
					$c->get(Expiration::class)
				);
			}
			return new ExpireGroupVersionsPlaceholder();
		});

		$context->registerService(\OCA\GroupFolders\BackgroundJob\ExpireGroupVersions::class, function (IAppContainer $c) {
			if (interface_exists('OCA\Files_Versions\Versions\IVersionBackend')) {
				return new \OCA\GroupFolders\BackgroundJob\ExpireGroupVersions(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(TrashBackend::class),
					$c->get(Expiration::class),
					$c->get(IConfig::class)
				);
			}
			return new \OCA\GroupFolders\BackgroundJob\ExpireGroupVersionsPlaceholder();
		});

		$context->registerService(ACLManagerFactory::class, function (IAppContainer $c) {
			$rootFolderProvider = function () use ($c) {
				return $c->getServer()->getRootFolder();
			};
			return new ACLManagerFactory(
				$c->get(RuleManager::class),
				$rootFolderProvider
			);
		});

		$context->registerServiceAlias(IUserMappingManager::class, UserMappingManager::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IMountProviderCollection $mountProviderCollection, CacheListener $cacheListener, IGroupManager $groupManager) {
			$mountProviderCollection->registerProvider($this->getMountProvider());

			$groupManager->listen('\OC\Group', 'postDelete', function (IGroup $group) {
				$this->getFolderManager()->deleteGroup($group->getGID());
			});
			$cacheListener->listen();
		});
	}

	/**
	 * @return MountProvider
	 */
	public function getMountProvider() {
		return $this->getContainer()->get(MountProvider::class);
	}

	/**
	 * @return FolderManager
	 */
	public function getFolderManager() {
		return $this->getContainer()->get(FolderManager::class);
	}
}
