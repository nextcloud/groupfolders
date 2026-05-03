<?php
// Fix for Team folders visible but not accessible after NC 33 upgrade
// File: apps/teamfolders/lib/Controller/FolderController.php

namespace OCA\TeamFolders\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\TeamFolders\Service\TeamFolderService;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class FolderController extends Controller {
    private $userSession;
    private $teamFolderService;
    private $rootFolder;

    public function __construct(
        $appName,
        IRequest $request,
        IUserSession $userSession,
        TeamFolderService $teamFolderService,
        IRootFolder $rootFolder
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->teamFolderService = $teamFolderService;
        $this->rootFolder = $rootFolder;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAccessibleFolders() {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }

        $userId = $user->getUID();
        $allFolders = $this->teamFolderService->getAllFolders();
        $accessibleFolders = [];

        foreach ($allFolders as $folder) {
            // Check if user has access to this team folder
            $folderId = $folder->getId();
            $folderNode = null;

            try {
                // Try to get the folder node for the user
                $userFolder = $this->rootFolder->getUserFolder($userId);
                $folderNode = $userFolder->get($folder->getMountPoint());
                
                // Verify actual access by checking permissions
                if ($folderNode->isReadable() && $folderNode->isShareable()) {
                    $accessibleFolders[] = [
                        'id' => $folderId,
                        'name' => $folder->getName(),
                        'mountPoint' => $folder->getMountPoint(),
                        'permissions' => $folderNode->getPermissions()
                    ];
                }
            } catch (NotFoundException $e) {
                // Folder not found for this user - skip
                continue;
            } catch (NotPermittedException $e) {
                // User doesn't have permission - skip
                continue;
            } catch (\Exception $e) {
                // Log other errors but continue
                \OC::$server->getLogger()->error(
                    'Error accessing team folder: ' . $e->getMessage(),
                    ['app' => 'teamfolders']
                );
                continue;
            }
        }

        return new JSONResponse($accessibleFolders);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getFolderContents($folderId) {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }

        $userId = $user->getUID();
        
        try {
            $folder = $this->teamFolderService->getFolder($folderId);
            if ($folder === null) {
                return new JSONResponse(['error' => 'Folder not found'], 404);
            }

            $userFolder = $this->rootFolder->getUserFolder($userId);
            $folderNode = $userFolder->get($folder->getMountPoint());

            // Check if user has read access
            if (!$folderNode->isReadable()) {
                return new JSONResponse(['error' => 'Access denied'], 403);
            }

            $contents = [];
            $nodes = $folderNode->getDirectoryListing();
            
            foreach ($nodes as $node) {
                $contents[] = [
                    'id' => $node->getId(),
                    'name' => $node->getName(),
                    'type' => $node->getType(),
                    'mtime' => $node->getMTime(),
                    'size' => $node->getSize(),
                    'permissions' => $node->getPermissions()
                ];
            }

            return new JSONResponse([
                'folder' => [
                    'id' => $folderId,
                    'name' => $folder->getName(),
                    'mountPoint' => $folder->getMountPoint()
                ],
                'contents' => $contents
            ]);

        } catch (NotFoundException $e) {
            return new JSONResponse(['error' => 'Folder not found'], 404);
        } catch (NotPermittedException $e) {
            return new JSONResponse(['error' => 'Access denied'], 403);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error(
                'Error accessing folder contents: ' . $e->getMessage(),
                ['app' => 'teamfolders']
            );
            return new JSONResponse(['error' => 'Internal server error'], 500);
        }
    }
}
