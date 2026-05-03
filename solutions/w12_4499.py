<?php
// Fix for Team folders visible but not accessible after NC 33 upgrade
// File: apps/teamfolders/lib/Controller/FolderController.php

namespace OCA\TeamFolders\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\TeamFolders\Service\FolderService;

class FolderController extends Controller {
    
    private $folderService;
    private $userSession;
    
    public function __construct(
        $appName,
        IRequest $request,
        FolderService $folderService,
        IUserSession $userSession
    ) {
        parent::__construct($appName, $request);
        $this->folderService = $folderService;
        $this->userSession = $userSession;
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAccessibleFolders() {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        
        $userId = $user->getUID();
        $folders = $this->folderService->getFoldersForUser($userId);
        
        // Fix: Ensure folders have proper permissions after NC 33 upgrade
        $accessibleFolders = array_filter($folders, function($folder) use ($userId) {
            return $this->folderService->hasAccess($folder, $userId);
        });
        
        return new JSONResponse(array_values($accessibleFolders));
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getFolderContent($folderId) {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'Not authenticated'], 401);
        }
        
        $userId = $user->getUID();
        
        // Fix: Check access before returning content
        if (!$this->folderService->hasAccessById($folderId, $userId)) {
            return new JSONResponse(['error' => 'Access denied'], 403);
        }
        
        $content = $this->folderService->getFolderContent($folderId, $userId);
        return new JSONResponse($content);
    }
}
