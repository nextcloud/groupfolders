<?php
// OCA\TeamFolders\AppInfo\Application.php

namespace OCA\TeamFolders\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCA\TeamFolders\Folder\FolderManager;
use OCA\TeamFolders\Mount\MountProvider;
use OCA\TeamFolders\Service\TeamFolderService;

class Application extends App implements IBootstrap {
    public const APP_ID = 'teamfolders';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerService(MountProvider::class, function ($c) {
            return new MountProvider(
                $c->get(IRootFolder::class),
                $c->get(FolderManager::class),
                $c->get(IGroupManager::class),
                $c->get(IUserSession::class),
                $c->get(IManager::class)
            );
        });
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (MountProvider $mountProvider) {
            $mountProvider->register();
        });
    }
}
