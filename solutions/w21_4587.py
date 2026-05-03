<?php
// Fix for xmlParseEntityRef: no name when a group name contains &
// In the file: apps/teamfolders/lib/Controller/ShareController.php

namespace OCA\TeamFolders\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OCA\TeamFolders\Service\TeamFolderService;

class ShareController extends Controller {
    
    /** @var IGroupManager */
    private $groupManager;
    
    /** @var IUserManager */
    private $userManager;
    
    /** @var ShareManager */
    private $shareManager;
    
    /** @var TeamFolderService */
    private $teamFolderService;
    
    public function __construct(
        $appName,
        IRequest $request,
        IGroupManager $groupManager,
        IUserManager $userManager,
        ShareManager $shareManager,
        TeamFolderService $teamFolderService
    ) {
        parent::__construct($appName, $request);
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->teamFolderService = $teamFolderService;
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function setPermissions($folderId, $nodeId, $shareType, $shareWith, $permissions) {
        try {
            // Sanitize shareWith to prevent XML parsing issues
            $shareWith = htmlspecialchars($shareWith, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            
            // Validate folder exists
            $folder = $this->teamFolderService->getFolder($folderId);
            if (!$folder) {
                return new JSONResponse(['error' => 'Folder not found'], 404);
            }
            
            // Validate node exists
            $node = $this->teamFolderService->getNode($nodeId);
            if (!$node) {
                return new JSONResponse(['error' => 'Node not found'], 404);
            }
            
            // Validate share type
            if (!in_array($shareType, [0, 1])) { // 0 = user, 1 = group
                return new JSONResponse(['error' => 'Invalid share type'], 400);
            }
            
            // Validate permissions
            $validPermissions = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31];
            if (!in_array($permissions, $validPermissions)) {
                return new JSONResponse(['error' => 'Invalid permissions'], 400);
            }
            
            // Check if shareWith exists
            if ($shareType === 0) {
                $user = $this->userManager->get($shareWith);
                if (!$user) {
                    return new JSONResponse(['error' => 'User not found'], 404);
                }
            } elseif ($shareType === 1) {
                $group = $this->groupManager->get($shareWith);
                if (!$group) {
                    return new JSONResponse(['error' => 'Group not found'], 404);
                }
            }
            
            // Set permissions
            $this->teamFolderService->setPermissions($folderId, $nodeId, $shareType, $shareWith, $permissions);
            
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getShares($folderId, $nodeId) {
        try {
            $folder = $this->teamFolderService->getFolder($folderId);
            if (!$folder) {
                return new JSONResponse(['error' => 'Folder not found'], 404);
            }
            
            $node = $this->teamFolderService->getNode($nodeId);
            if (!$node) {
                return new JSONResponse(['error' => 'Node not found'], 404);
            }
            
            $shares = $this->teamFolderService->getShares($folderId, $nodeId);
            
            // Sanitize shareWith values for XML output
            foreach ($shares as &$share) {
                if (isset($share['shareWith'])) {
                    $share['shareWith'] = htmlspecialchars($share['shareWith'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                }
            }
            
            return new JSONResponse($shares);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function removeShare($folderId, $nodeId, $shareType, $shareWith) {
        try {
            // Sanitize shareWith
            $shareWith = htmlspecialchars($shareWith, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            
            $folder = $this->teamFolderService->getFolder($folderId);
            if (!$folder) {
                return new JSONResponse(['error' => 'Folder not found'], 404);
            }
            
            $node = $this->teamFolderService->getNode($nodeId);
            if (!$node) {
                return new JSONResponse(['error' => 'Node not found'], 404);
            }
            
            $this->teamFolderService->removeShare($folderId, $nodeId, $shareType, $shareWith);
            
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(['error' => $e->getMessage()], 500);
        }
    }
}
