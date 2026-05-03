<?php
// Fix for xmlParseEntityRef: no name when a group name contains &
// In file: apps/teamfolders/lib/Controller/SharingController.php

namespace OCA\TeamFolders\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OCA\TeamFolders\Service\TeamFolderService;

class SharingController extends Controller {
    
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
    public function setPermissions() {
        $folderId = (int)$this->request->getParam('folderId');
        $shareType = (int)$this->request->getParam('shareType');
        $shareWith = $this->request->getParam('shareWith');
        $permissions = (int)$this->request->getParam('permissions');
        
        // Sanitize shareWith to prevent XML parsing issues
        $shareWith = $this->sanitizeShareWith($shareWith);
        
        // Validate and process the sharing
        try {
            $result = $this->teamFolderService->setPermissions(
                $folderId,
                $shareType,
                $shareWith,
                $permissions
            );
            
            return new JSONResponse(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Sanitize shareWith value to prevent XML parsing issues
     * 
     * @param string $shareWith
     * @return string
     */
    private function sanitizeShareWith($shareWith) {
        if ($shareWith === null) {
            return '';
        }
        
        // Encode & to &amp; to prevent XML parsing errors
        $shareWith = htmlspecialchars($shareWith, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        
        return $shareWith;
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getShares() {
        $folderId = (int)$this->request->getParam('folderId');
        
        try {
            $shares = $this->teamFolderService->getShares($folderId);
            
            // Sanitize share names in the response
            foreach ($shares as &$share) {
                if (isset($share['shareWith'])) {
                    $share['shareWith'] = $this->sanitizeShareWith($share['shareWith']);
                }
                if (isset($share['displayName'])) {
                    $share['displayName'] = $this->sanitizeShareWith($share['displayName']);
                }
            }
            
            return new JSONResponse(['success' => true, 'data' => $shares]);
        } catch (\Exception $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function searchSharees() {
        $search = $this->request->getParam('search');
        $search = $this->sanitizeShareWith($search);
        $itemType = $this->request->getParam('itemType', 'folder');
        $page = (int)$this->request->getParam('page', 1);
        $perPage = (int)$this->request->getParam('perPage', 200);
        
        try {
            $result = $this->teamFolderService->searchSharees($search, $itemType, $page, $perPage);
            
            // Sanitize results
            if (isset($result['groups'])) {
                foreach ($result['groups'] as &$group) {
                    if (isset($group['label'])) {
                        $group['label'] = $this->sanitizeShareWith($group['label']);
                    }
                }
            }
            
            return new JSONResponse(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return new JSONResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
