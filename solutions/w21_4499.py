<?php
// OCA\TeamFolders\AppInfo\Application.php

namespace OCA\TeamFolders\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Folder;
use OCP\Share\IShare;
use OCP\Share\Exceptions\ShareNotFound;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'teamfolders';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register event listeners
        $context->registerEventListener(
            \OCP\Files\Events\Node\BeforeNodeDeleted::class,
            \OCA\TeamFolders\Listener\BeforeNodeDeletedListener::class
        );
        
        // Register hooks
        $context->registerEventListener(
            \OCP\Files\Events\Node\NodeCreated::class,
            \OCA\TeamFolders\Listener\NodeCreatedListener::class
        );
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            IRootFolder $rootFolder,
            IUserSession $userSession,
            IManager $shareManager,
            IGroupManager $groupManager,
            IConfig $config,
            IDBConnection $db,
            IL10N $l10n,
            IURLGenerator $urlGenerator,
            IUserManager $userManager,
            ISecureRandom $secureRandom,
            LoggerInterface $logger
        ) {
            // Fix for NC 33: Ensure team folders are properly accessible
            $this->fixTeamFolderAccess($rootFolder, $userSession, $shareManager, $groupManager, $config, $db, $logger);
        });
    }

    private function fixTeamFolderAccess(
        IRootFolder $rootFolder,
        IUserSession $userSession,
        IManager $shareManager,
        IGroupManager $groupManager,
        IConfig $config,
        IDBConnection $db,
        LoggerInterface $logger
    ): void {
        $user = $userSession->getUser();
        if ($user === null) {
            return;
        }

        $userId = $user->getUID();
        $userFolder = $rootFolder->getUserFolder($userId);
        
        // Get all team folders from database
        $query = $db->getQueryBuilder();
        $query->select('*')
            ->from('teamfolders_folders')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->orWhere($query->expr()->eq('group_id', $query->createNamedParameter($userId)));
        
        $result = $query->execute();
        $teamFolders = $result->fetchAll();
        $result->closeCursor();

        foreach ($teamFolders as $folderData) {
            try {
                $folderId = (int)$folderData['folder_id'];
                $folderName = $folderData['folder_name'];
                
                // Check if the folder exists in the root
                try {
                    $folder = $userFolder->get($folderName);
                    if ($folder instanceof Folder) {
                        // Check if the share still exists
                        $shares = $shareManager->getSharesBy($userId, IShare::TYPE_FOLDER, $folder, false, 1);
                        if (empty($shares)) {
                            // Re-create the share
                            $share = $shareManager->newShare();
                            $share->setNode($folder)
                                ->setShareType(IShare::TYPE_GROUP)
                                ->setSharedWith($folderData['group_id'])
                                ->setPermissions(\OCP\Constants::PERMISSION_ALL)
                                ->setSharedBy($userId);
                            $shareManager->createShare($share);
                            $logger->info('Re-created share for team folder: ' . $folderName);
                        }
                    }
                } catch (NotFoundException $e) {
                    // Folder doesn't exist in user's root, try to find it in the filesystem
                    $this->restoreTeamFolderAccess($folderId, $folderName, $userId, $rootFolder, $shareManager, $groupManager, $db, $logger);
                }
            } catch (\Exception $e) {
                $logger->error('Error fixing team folder access: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    private function restoreTeamFolderAccess(
        int $folderId,
        string $folderName,
        string $userId,
        IRootFolder $rootFolder,
        IManager $shareManager,
        IGroupManager $groupManager,
        IDBConnection $db,
        LoggerInterface $logger
    ): void {
        try {
            // Get the actual folder from the filesystem
            $query = $db->getQueryBuilder();
            $query->select('file_id')
                ->from('teamfolders_folders')
                ->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
            $result = $query->execute();
            $fileData = $result->fetch();
            $result->closeCursor();

            if ($fileData === false) {
                return;
            }

            $fileId = (int)$fileData['file_id'];
            
            // Find the folder by file ID
            $nodes = $rootFolder->getById($fileId);
            if (empty($nodes)) {
                $logger->warning('Team folder not found in filesystem: ' . $folderName);
                return;
            }

            $folder = $nodes[0];
            if (!($folder instanceof Folder)) {
                return;
            }

            // Get the group for this team folder
            $query = $db->getQueryBuilder();
            $query->select('group_id')
                ->from('teamfolders_groups')
                ->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
            $result = $query->execute();
            $groupData = $result->fetchAll();
            $result->closeCursor();

            foreach ($groupData as $group) {
                $groupId = $group['group_id'];
                
                // Check if user is in the group
                if (!$groupManager->isInGroup($userId, $groupId)) {
                    continue;
                }

                // Create a share for the user
                $share = $shareManager->newShare();
                $share->setNode($folder)
                    ->setShareType(IShare::TYPE_USER)
                    ->setSharedWith($userId)
                    ->setPermissions(\OCP\Constants::PERMISSION_ALL)
                    ->setSharedBy('admin');
                
                try {
                    $shareManager->createShare($share);
                    $logger->info('Restored access to team folder: ' . $folderName . ' for user: ' . $userId);
                } catch (\Exception $e) {
                    $logger->error('Failed to restore access: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $logger->error('Error restoring team folder access: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
