<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\AppInfo;

use OC\Files\Node\LazyFolder;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\UserMapping\UserMappingManager;
use OCA\GroupFolders\AuthorizedAdminSettingMiddleware;
use OCA\GroupFolders\BackgroundJob\ExpireGroupPlaceholder;
use OCA\GroupFolders\BackgroundJob\ExpireGroupTrash as ExpireGroupTrashJob;
use OCA\GroupFolders\BackgroundJob\ExpireGroupVersions as ExpireGroupVersionsJob;
use OCA\GroupFolders\CacheListener;
use OCA\GroupFolders\Command\ExpireGroup\ExpireGroupBase;
use OCA\GroupFolders\Command\ExpireGroup\ExpireGroupTrash;
use OCA\GroupFolders\Command\ExpireGroup\ExpireGroupVersions;
use OCA\GroupFolders\Command\ExpireGroup\ExpireGroupVersionsTrash;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Listeners\CircleDestroyedEventListener;
use OCA\GroupFolders\Listeners\LoadAdditionalScriptsListener;
use OCA\GroupFolders\Listeners\NodeRenamedListener;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Trash\TrashManager;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCA\GroupFolders\Versions\GroupVersionsMapper;
use OCA\GroupFolders\Versions\VersionsBackend;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'groupfolders';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public const APPS_USE_GROUPFOLDERS = [
		'workspace'
	];

	public function register(IRegistrationContext $context): void {
		/** Register $principalBackend for the DAV collection */
		$context->registerServiceAlias('principalBackend', Principal::class);

		$context->registerCapability(Capabilities::class);

		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(CircleDestroyedEvent::class, CircleDestroyedEventListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, NodeRenamedListener::class);

		$context->registerService('GroupAppFolder', function (ContainerInterface $c): Folder {
			/** @var IRootFolder $rootFolder */
			$rootFolder = $c->get(IRootFolder::class);

			return new LazyFolder($rootFolder, function () use ($rootFolder): Folder {
				try {
					/** @var Folder $folder */
					$folder = $rootFolder->get('__groupfolders');

					return $folder;
				} catch (NotFoundException) {
					return $rootFolder->newFolder('__groupfolders');
				}
			}, [
				'path' => '/__groupfolders'
			]);
		});

		$context->registerService(MountProvider::class, function (ContainerInterface $c): MountProvider {
			$rootProvider = fn (): Folder => $c->get('GroupAppFolder');
			/** @var IAppConfig $config */
			$config = $c->get(IAppConfig::class);
			$allowRootShare = $config->getValueString('groupfolders', 'allow_root_share', 'true') === 'true';
			$enableEncryption = $config->getValueString('groupfolders', 'enable_encryption', 'false') === 'true';

			return new MountProvider(
				$c->get(FolderManager::class),
				$rootProvider,
				$c->get(ACLManagerFactory::class),
				$c->get(IUserSession::class),
				$c->get(IRequest::class),
				$c->get(IMountProviderCollection::class),
				$c->get(IDBConnection::class),
				$c->get(ICacheFactory::class)->createLocal('groupfolders'),
				$allowRootShare,
				$enableEncryption
			);
		});

		$context->registerService(TrashBackend::class, function (ContainerInterface $c): TrashBackend {
			$trashBackend = new TrashBackend(
				$c->get(FolderManager::class),
				$c->get(TrashManager::class),
				$c->get('GroupAppFolder'),
				$c->get(MountProvider::class),
				$c->get(ACLManagerFactory::class),
				$c->get(IRootFolder::class),
				$c->get(LoggerInterface::class),
				$c->get(IUserManager::class),
				$c->get(IUserSession::class),
				$c->get(IMountManager::class),
				$c->get(IStorageFactory::class),
			);
			$hasVersionApp = interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class);
			if ($hasVersionApp) {
				$trashBackend->setVersionsBackend($c->get(VersionsBackend::class));
			}

			return $trashBackend;
		});

		$context->registerService(VersionsBackend::class, fn (ContainerInterface $c): VersionsBackend => new VersionsBackend(
			$c->get(IRootFolder::class),
			$c->get('GroupAppFolder'),
			$c->get(MountProvider::class),
			$c->get(LoggerInterface::class),
			$c->get(GroupVersionsMapper::class),
			$c->get(IMimeTypeLoader::class),
			$c->get(IUserSession::class),
		));

		$context->registerService(ExpireGroupBase::class, function (ContainerInterface $c): ExpireGroupBase {
			// Multiple implementation of this class exists depending on if the trash and versions
			// backends are enabled.

			$hasVersionApp = interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class);
			$hasTrashApp = interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class);

			if ($hasVersionApp && $hasTrashApp) {
				return new ExpireGroupVersionsTrash(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(IEventDispatcher::class),
					$c->get(TrashBackend::class),
					$c->get(Expiration::class)
				);
			}

			if ($hasVersionApp) {
				return new ExpireGroupVersions(
					$c->get(GroupVersionsExpireManager::class),
					$c->get(IEventDispatcher::class),
				);
			}

			if ($hasTrashApp) {
				return new ExpireGroupTrash(
					$c->get(TrashBackend::class),
					$c->get(Expiration::class)
				);
			}

			return new ExpireGroupBase();
		});

		$context->registerService(\OCA\GroupFolders\BackgroundJob\ExpireGroupVersions::class, function (ContainerInterface $c): TimedJob {
			if (interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class)) {
				return new ExpireGroupVersionsJob(
					$c->get(ITimeFactory::class),
					$c->get(GroupVersionsExpireManager::class),
					$c->get(IAppConfig::class),
					$c->get(FolderManager::class),
					$c->get(LoggerInterface::class),
				);
			}

			return new ExpireGroupPlaceholder($c->get(ITimeFactory::class));
		});

		$context->registerService(\OCA\GroupFolders\BackgroundJob\ExpireGroupTrash::class, function (ContainerInterface $c): TimedJob {
			if (interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class)) {
				return new ExpireGroupTrashJob(
					$c->get(TrashBackend::class),
					$c->get(Expiration::class),
					$c->get(IAppConfig::class),
					$c->get(ITimeFactory::class)
				);
			}

			return new ExpireGroupPlaceholder($c->get(ITimeFactory::class));
		});

		$context->registerService(ACLManagerFactory::class, function (ContainerInterface $c): ACLManagerFactory {
			$rootFolderProvider = fn (): \OCP\Files\IRootFolder => $c->get(IRootFolder::class);

			return new ACLManagerFactory(
				$c->get(RuleManager::class),
				$c->get(TrashManager::class),
				$c->get(IAppConfig::class),
				$c->get(LoggerInterface::class),
				$c->get(IUserMappingManager::class),
				$rootFolderProvider
			);
		});

		$context->registerServiceAlias(IUserMappingManager::class, UserMappingManager::class);

		$context->registerMiddleware(AuthorizedAdminSettingMiddleware::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (IMountProviderCollection $mountProviderCollection, CacheListener $cacheListener, IEventDispatcher $eventDispatcher): void {
			$mountProviderCollection->registerProvider(Server::get(MountProvider::class));

			$eventDispatcher->addListener(GroupDeletedEvent::class, function (GroupDeletedEvent $event): void {
				Server::get(FolderManager::class)->deleteGroup($event->getGroup()->getGID());
			});
			$cacheListener->listen();
		});
	}
}
