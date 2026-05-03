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
use OCP\Files\NotPermittedException;
use OCP\PreConditionNotMetException;
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
            // Listen for team folder creation events
            $this->getContainer()->getServer()->getEventDispatcher()->addListener(
                'OCA\\TeamFolders\\Events\\TeamFolderCreatedEvent',
                function ($event) use ($rootFolder, $config, $groupManager, $userManager, $shareManager, $logger) {
                    $this->handleTeamFolderCreation($event, $rootFolder, $config, $groupManager, $userManager, $shareManager, $logger);
                }
            );
        });
    }

    private function handleTeamFolderCreation(
        $event,
        IRootFolder $rootFolder,
        IConfig $config,
        IGroupManager $groupManager,
        IUserManager $userManager,
        ShareManager $shareManager,
        LoggerInterface $logger
    ): void {
        $teamFolderId = $event->getFolderId();
        $folderName = $event->getFolderName();
        $groupIds = $event->getGroupIds();

        // Get the data directory from config, fallback to default
        $dataDirectory = $config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');

        try {
            // Get or create the team folders root
            $teamFoldersRoot = $this->getTeamFoldersRoot($rootFolder, $dataDirectory);

            // Create the new team folder
            if (!$teamFoldersRoot->nodeExists($folderName)) {
                $newFolder = $teamFoldersRoot->newFolder($folderName);
                $logger->info('Created team folder: ' . $folderName . ' with ID: ' . $teamFolderId);
            } else {
                $newFolder = $teamFoldersRoot->get($folderName);
                $logger->info('Team folder already exists: ' . $folderName);
            }

            // Set folder permissions for groups
            $this->setFolderPermissions($newFolder, $groupIds, $groupManager, $shareManager, $logger);

            // Store the mapping in app config
            $config->setAppValue('teamfolders', 'folder_' . $teamFolderId, $folderName);
            $config->setAppValue('teamfolders', 'folder_' . $teamFolderId . '_groups', json_encode($groupIds));

        } catch (NotFoundException $e) {
            $logger->error('Could not find team folders root: ' . $e->getMessage());
        } catch (NotPermittedException $e) {
            $logger->error('Permission denied creating team folder: ' . $e->getMessage());
        } catch (\Exception $e) {
            $logger->error('Error creating team folder: ' . $e->getMessage());
        }
    }

    private function getTeamFoldersRoot(IRootFolder $rootFolder, string $dataDirectory): \OCP\Files\Folder {
        // Navigate to the data directory
        $dataFolder = $rootFolder->get($dataDirectory);
        
        // Create or get the TeamFolders directory
        if (!$dataFolder->nodeExists('TeamFolders')) {
            return $dataFolder->newFolder('TeamFolders');
        }
        return $dataFolder->get('TeamFolders');
    }

    private function setFolderPermissions(
        \OCP\Files\Folder $folder,
        array $groupIds,
        IGroupManager $groupManager,
        ShareManager $shareManager,
        LoggerInterface $logger
    ): void {
        foreach ($groupIds as $groupId) {
            $group = $groupManager->get($groupId);
            if ($group === null) {
                $logger->warning('Group not found: ' . $groupId);
                continue;
            }

            // Share folder with the group
            try {
                $share = $shareManager->newShare();
                $share->setNode($folder)
                    ->setShareType(\OCP\Share\IShare::TYPE_GROUP)
                    ->setSharedWith($groupId)
                    ->setPermissions(\OCP\Constants::PERMISSION_ALL);
                
                $shareManager->createShare($share);
                $logger->info('Shared team folder with group: ' . $groupId);
            } catch (\Exception $e) {
                $logger->error('Failed to share folder with group ' . $groupId . ': ' . $e->getMessage());
            }
        }
    }
}
