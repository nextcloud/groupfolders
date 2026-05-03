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
use OCP\Share\IManager as ShareManager;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'teamfolders';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register event listeners if needed
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            IRootFolder $rootFolder,
            IConfig $config,
            IGroupManager $groupManager,
            IUserManager $userManager,
            ShareManager $shareManager,
            LoggerInterface $logger
        ) {
            // Fix: Ensure datadirectory is properly resolved
            $dataDir = $config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
            
            // Register event listener for folder creation
            $eventDispatcher = \OC::$server->getEventDispatcher();
            $eventDispatcher->addListener('OCA\TeamFolders::createFolder', function ($event) use (
                $rootFolder, $config, $groupManager, $userManager, $shareManager, $logger, $dataDir
            ) {
                $folderName = $event->getArgument('folderName');
                $groups = $event->getArgument('groups');
                
                try {
                    // Ensure we're using the correct data directory
                    $rootFolder->getMountPoint($dataDir);
                    
                    // Create the team folder
                    $teamFolder = $rootFolder->newFolder($folderName);
                    
                    // Set permissions for groups
                    foreach ($groups as $group) {
                        $groupObj = $groupManager->get($group['gid']);
                        if ($groupObj !== null) {
                            $share = $shareManager->newShare();
                            $share->setNode($teamFolder)
                                ->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
                                ->setSharedWith($group['gid'])
                                ->setPermissions($group['permissions']);
                            $shareManager->createShare($share);
                        }
                    }
                    
                    // Store folder configuration
                    $config->setAppValue(self::APP_ID, 'folder_' . $folderName, json_encode([
                        'name' => $folderName,
                        'groups' => $groups,
                        'created' => time()
                    ]));
                    
                    $logger->info('Team folder created successfully: ' . $folderName);
                    
                } catch (NotFoundException $e) {
                    $logger->error('Failed to create team folder: ' . $e->getMessage());
                    throw $e;
                }
            });
        });
    }
}
