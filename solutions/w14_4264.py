<?php

namespace OCA\TeamFolders\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCA\TeamFolders\Service\TeamFolderService;

class Application extends App implements IBootstrap {
    public const APP_ID = 'teamfolders';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register services
        $context->registerService(TeamFolderService::class, function ($c) {
            return new TeamFolderService(
                $c->query(IRootFolder::class),
                $c->query(IConfig::class),
                $c->query(IGroupManager::class),
                $c->query(IUserManager::class),
                $c->query(IUserSession::class),
                $c->query(IManager::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
        // Register hooks
        $context->injectFn(function (TeamFolderService $teamFolderService) {
            $teamFolderService->registerHooks();
        });
    }
}
