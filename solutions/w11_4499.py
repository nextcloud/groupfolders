<?php
/**
 * @copyright Copyright (c) 2024, Your Name
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\TeamFolders\AppInfo;

use OCA\TeamFolders\Listener\GroupFolderCreatedListener;
use OCA\TeamFolders\Listener\GroupFolderDeletedListener;
use OCA\TeamFolders\Listener\GroupFolderRenamedListener;
use OCA\TeamFolders\Listener\GroupFolderUpdatedListener;
use OCA\TeamFolders\Listener\GroupFolderMovedListener;
use OCA\TeamFolders\Listener\GroupFolderPermissionUpdatedListener;
use OCA\TeamFolders\Listener\GroupFolderMountPointUpdatedListener;
use OCA\TeamFolders\Listener\GroupFolderAclUpdatedListener;
use OCA\TeamFolders\Listener\GroupFolderManagerListener;
use OCA\TeamFolders\Middleware\TeamFolderMiddleware;
use OCA\TeamFolders\Service\TeamFolderService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\GroupCreatedEvent;
use OCP\Group\Events\GroupUpdatedEvent;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\Util;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'teamfolders';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register services
        $context->registerService(TeamFolderService::class, function (IServerContainer $c) {
            return new TeamFolderService(
                $c->get(IRootFolder::class),
                $c->get(IGroupManager::class),
                $c->get(IUserManager::class),
                $c->get(IManager::class),
                $c->get(IDBConnection::class),
                $c->get(IConfig::class),
                $c->get(IL10N::class),
                $c->get(IURLGenerator::class),
                $c->get(LoggerInterface::class)
            );
        });

        // Register middleware
        $context->registerMiddleware(TeamFolderMiddleware::class);

        // Register event listeners for group folder changes
        $context->registerEventListener(GroupCreatedEvent::class, GroupFolderCreatedListener::class);
        $context->registerEventListener(GroupDeletedEvent::class, GroupFolderDeletedListener::class);
        $context->registerEventListener(GroupUpdatedEvent::class, GroupFolderUpdatedListener::class);
        
        // Register custom event listeners
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderCreated', GroupFolderCreatedListener::class);
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderDeleted', GroupFolderDeletedListener::class);
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderRenamed', GroupFolderRenamedListener::class);
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderMoved', GroupFolderMovedListener::class);
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderPermissionUpdated', GroupFolderPermissionUpdatedListener::class);
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderMountPointUpdated', GroupFolderMountPointUpdatedListener::class);
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderAclUpdated', GroupFolderAclUpdatedListener::class);
        $context->registerEventListener('OCA\GroupFolders\Events\GroupFolderManagerUpdated', GroupFolderManagerListener::class);
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            IUserSession $userSession,
            IGroupManager $groupManager,
            TeamFolderService $teamFolderService,
            LoggerInterface $logger
        ) {
            // Check if user is logged in
            $user = $userSession->getUser();
            if ($user === null) {
                return;
            }

            // Get user's groups
            $userGroups = $groupManager->getUserGroupIds($user);
            
            // Check and fix team folder visibility issues
            try {
                $teamFolderService->fixTeamFolderVisibility($user, $userGroups);
            } catch (\Exception $e) {
                $logger->error('Failed to fix team folder visibility: ' . $e->getMessage(), [
                    'app' => self::APP_ID,
                    'user' => $user->getUID()
                ]);
            }
        });
    }
}
