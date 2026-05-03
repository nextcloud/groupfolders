<?php
// OCA\TeamFolders\AppInfo\Application.php

namespace OCA\TeamFolders\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCA\TeamFolders\Folder\FolderManager;
use OCA\TeamFolders\Mount\TeamFolderMountProvider;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\QueryException;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
    public const APP_ID = 'teamfolders';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerMountProvider(TeamFolderMountProvider::class);
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            IRootFolder $rootFolder,
            IUserSession $userSession,
            IUserManager $userManager,
            IGroupManager $groupManager,
            IManager $shareManager,
            FolderManager $folderManager,
            IMountProviderCollection $mountProviderCollection,
            IConfig $config,
            IL10N $l10n,
            IURLGenerator $urlGenerator,
            IRequest $request,
            LoggerInterface $logger
        ) {
            // Fix for NC 33: Ensure team folders are properly mounted and accessible
            $user = $userSession->getUser();
            if ($user === null) {
                return;
            }

            $userId = $user->getUID();
            $userFolder = $rootFolder->getUserFolder($userId);
            
            // Check if team folders mount point exists
            $teamFoldersMountPoint = 'Team folders';
            try {
                if (!$userFolder->nodeExists($teamFoldersMountPoint)) {
                    $userFolder->newFolder($teamFoldersMountPoint);
                }
            } catch (NotPermittedException $e) {
                $logger->error('Cannot create team folders mount point', ['exception' => $e]);
                return;
            }

            // Get all team folders for this user
            $teamFolders = $folderManager->getFoldersForUser($user);
            
            foreach ($teamFolders as $folder) {
                $folderId = $folder->getId();
                $folderName = $folder->getName();
                
                // Check if the folder is mounted in the user's root
                $mountPoint = $teamFoldersMountPoint . '/' . $folderName;
                
                try {
                    if ($userFolder->nodeExists($mountPoint)) {
                        $node = $userFolder->get($mountPoint);
                        if ($node instanceof Folder) {
                            // Verify accessibility
                            try {
                                $node->getDirectoryListing();
                            } catch (NotPermittedException $e) {
                                // Re-mount if not accessible
                                $this->remountTeamFolder($folderId, $userId, $rootFolder, $mountProviderCollection, $logger);
                            }
                        }
                    } else {
                        // Mount the folder
                        $this->mountTeamFolder($folderId, $userId, $rootFolder, $mountProviderCollection, $logger);
                    }
                } catch (NotFoundException $e) {
                    // Mount the folder
                    $this->mountTeamFolder($folderId, $userId, $rootFolder, $mountProviderCollection, $logger);
                }
            }

            // Fix share permissions for team folders
            $this->fixSharePermissions($user, $folderManager, $shareManager, $rootFolder, $logger);
        });
    }

    private function mountTeamFolder(int $folderId, string $userId, IRootFolder $rootFolder, IMountProviderCollection $mountProviderCollection, LoggerInterface $logger): void {
        try {
            $mountProvider = new TeamFolderMountProvider(
                $rootFolder,
                $this->getContainer()->query('OCP\IConfig'),
                $this->getContainer()->query('OCP\IGroupManager'),
                $this->getContainer()->query('OCA\TeamFolders\Folder\FolderManager'),
                $this->getContainer()->query('OCP\IUserManager'),
                $this->getContainer()->query('Psr\Log\LoggerInterface')
            );
            
            $mounts = $mountProvider->getMountsForUser($this->getContainer()->query('OCP\IUserManager')->get($userId));
            foreach ($mounts as $mount) {
                if ($mount->getFolderId() === $folderId) {
                    $mountProviderCollection->registerMount($mount);
                    break;
                }
            }
        } catch (\Exception $e) {
            $logger->error('Failed to mount team folder', [
                'folderId' => $folderId,
                'userId' => $userId,
                'exception' => $e
            ]);
        }
    }

    private function remountTeamFolder(int $folderId, string $userId, IRootFolder $rootFolder, IMountProviderCollection $mountProviderCollection, LoggerInterface $logger): void {
        try {
            // Unmount existing
            $mountProviderCollection->removeMount($folderId);
            
            // Re-mount
            $this->mountTeamFolder($folderId, $userId, $rootFolder, $mountProviderCollection, $logger);
        } catch (\Exception $e) {
            $logger->error('Failed to remount team folder', [
                'folderId' => $folderId,
                'userId' => $userId,
                'exception' => $e
            ]);
        }
    }

    private function fixSharePermissions(\OCP\IUser $user, FolderManager $folderManager, IManager $shareManager, IRootFolder $rootFolder, LoggerInterface $logger): void {
        $userId = $user->getUID();
        $userFolder = $rootFolder->getUserFolder($userId);
        
        try {
            $teamFoldersNode = $userFolder->get('Team folders');
            if ($teamFoldersNode instanceof Folder) {
                $folders = $teamFoldersNode->getDirectoryListing();
                foreach ($folders as $folder) {
                    if ($folder instanceof Folder) {
                        // Ensure proper share permissions
                        $shares = $shareManager->getSharesBy($user, \OCP\Share\IShare::TYPE_FOLDER, $folder, true);
                        foreach ($shares as $share) {
                            if ($share->getPermissions() !== \OCP\Constants::PERMISSION_ALL) {
                                $share->setPermissions(\OCP\Constants::PERMISSION_ALL);
                                $shareManager->updateShare($share);
                            }
                        }
                    }
                }
            }
        } catch (NotFoundException $e) {
            // Team folders mount point doesn't exist yet
        } catch (\Exception $e) {
            $logger->error('Failed to fix share permissions', ['exception' => $e]);
        }
    }
}
