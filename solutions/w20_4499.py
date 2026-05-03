<?php
// OCA\TeamFolders\AppInfo\Application.php

namespace OCA\TeamFolders\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'teamfolders';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register event listeners for folder creation
        $context->registerEventListener(
            \OCP\Files\Events\Node\NodeCreatedEvent::class,
            \OCA\TeamFolders\Listener\TeamFolderCreatedListener::class
        );
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            IUserSession $userSession,
            IManager $shareManager,
            IRootFolder $rootFolder,
            IConfig $config,
            IGroupManager $groupManager,
            LoggerInterface $logger
        ) {
            // Fix for NC 33: Ensure team folders are properly shared with members
            $user = $userSession->getUser();
            if ($user === null) {
                return;
            }

            $userId = $user->getUID();
            $teamFolders = $this->getTeamFolders($config, $rootFolder, $groupManager, $logger);

            foreach ($teamFolders as $folder) {
                try {
                    $this->ensureFolderAccessible($folder, $userId, $shareManager, $rootFolder, $logger);
                } catch (\Exception $e) {
                    $logger->error('Failed to fix team folder access: ' . $e->getMessage(), [
                        'app' => self::APP_ID,
                        'folder' => $folder->getName(),
                        'user' => $userId
                    ]);
                }
            }
        });
    }

    private function getTeamFolders(IConfig $config, IRootFolder $rootFolder, IGroupManager $groupManager, LoggerInterface $logger): array {
        $teamFolders = [];
        $folderConfigs = $config->getAppKeys(self::APP_ID);
        
        foreach ($folderConfigs as $key) {
            if (strpos($key, 'folder_') === 0) {
                $folderId = (int) substr($key, 7);
                $folderConfig = json_decode($config->getAppValue(self::APP_ID, $key, '{}'), true);
                
                if ($folderConfig && isset($folderConfig['folder_id'])) {
                    try {
                        $folder = $rootFolder->getById($folderConfig['folder_id']);
                        if ($folder) {
                            $teamFolders[] = $folder[0];
                        }
                    } catch (NotFoundException $e) {
                        $logger->warning('Team folder not found: ' . $folderConfig['folder_id'], ['app' => self::APP_ID]);
                    }
                }
            }
        }
        
        return $teamFolders;
    }

    private function ensureFolderAccessible(
        \OCP\Files\Folder $folder,
        string $userId,
        IManager $shareManager,
        IRootFolder $rootFolder,
        LoggerInterface $logger
    ): void {
        // Check if user already has access via share
        $shares = $shareManager->getSharesBy($userId, IShare::TYPE_FOLDER);
        $hasAccess = false;
        
        foreach ($shares as $share) {
            if ($share->getNodeId() === $folder->getId()) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            // Create a share for the user if they are a member of the team
            $teamFolderConfig = $this->getTeamFolderConfig($folder->getId());
            if ($teamFolderConfig && isset($teamFolderConfig['groups'])) {
                $user = \OC::$server->getUserManager()->get($userId);
                if ($user) {
                    foreach ($teamFolderConfig['groups'] as $groupId => $permissions) {
                        $group = \OC::$server->getGroupManager()->get($groupId);
                        if ($group && $group->inGroup($user)) {
                            try {
                                $share = $shareManager->newShare();
                                $share->setNode($folder)
                                    ->setShareType(IShare::TYPE_USER)
                                    ->setSharedBy('admin')
                                    ->setSharedWith($userId)
                                    ->setPermissions($permissions);
                                $shareManager->createShare($share);
                                $logger->info('Created share for team folder: ' . $folder->getName() . ' for user: ' . $userId, ['app' => self::APP_ID]);
                            } catch (\Exception $e) {
                                $logger->error('Failed to create share: ' . $e->getMessage(), ['app' => self::APP_ID]);
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    private function getTeamFolderConfig(int $folderId): ?array {
        $config = \OC::$server->getConfig();
        $key = 'folder_' . $folderId;
        $value = $config->getAppValue(self::APP_ID, $key, null);
        
        if ($value) {
            return json_decode($value, true);
        }
        
        return null;
    }
}
