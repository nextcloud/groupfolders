<?php
// OCA\TeamFolders\Folder\FolderManager.php

namespace OCA\TeamFolders\Folder;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OCA\TeamFolders\AppInfo\Application;
use OCP\Files\Folder;

class FolderManager {
    /** @var IRootFolder */
    private $rootFolder;
    
    /** @var IConfig */
    private $config;
    
    /** @var IGroupManager */
    private $groupManager;
    
    /** @var IUserManager */
    private $userManager;
    
    /** @var ShareManager */
    private $shareManager;
    
    public function __construct(
        IRootFolder $rootFolder,
        IConfig $config,
        IGroupManager $groupManager,
        IUserManager $userManager,
        ShareManager $shareManager
    ) {
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
    }
    
    /**
     * Create a new team folder
     *
     * @param string $mountPoint
     * @param array $groups
     * @return Folder
     * @throws \Exception
     */
    public function createFolder($mountPoint, array $groups = []) {
        // Get the data directory from config, with fallback
        $dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
        
        // Ensure the data directory exists
        if (!is_dir($dataDir)) {
            throw new \Exception('Data directory does not exist: ' . $dataDir);
        }
        
        // Get the root folder for the current user (admin)
        $userFolder = $this->rootFolder->getUserFolder(\OC::$server->getSession()->getUser()->getUID());
        
        // Check if team folders directory exists in data directory
        $teamFoldersDir = $dataDir . '/teamfolders';
        if (!is_dir($teamFoldersDir)) {
            if (!mkdir($teamFoldersDir, 0755, true)) {
                throw new \Exception('Could not create team folders directory');
            }
        }
        
        // Create the actual folder in the filesystem
        $folderPath = $teamFoldersDir . '/' . $mountPoint;
        if (is_dir($folderPath)) {
            throw new \Exception('Team folder already exists: ' . $mountPoint);
        }
        
        if (!mkdir($folderPath, 0755, true)) {
            throw new \Exception('Could not create team folder: ' . $mountPoint);
        }
        
        // Now create the folder in the user's file system
        try {
            $teamFolder = $userFolder->newFolder($mountPoint);
        } catch (\Exception $e) {
            // Clean up if filesystem creation fails
            rmdir($folderPath);
            throw $e;
        }
        
        // Store folder metadata
        $folderId = $teamFolder->getId();
        $this->storeFolderMetadata($folderId, $mountPoint, $groups);
        
        // Set up group permissions
        $this->setupGroupPermissions($folderId, $groups);
        
        return $teamFolder;
    }
    
    /**
     * Store folder metadata in the database
     *
     * @param int $folderId
     * @param string $mountPoint
     * @param array $groups
     */
    private function storeFolderMetadata($folderId, $mountPoint, array $groups) {
        $query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $query->insert('teamfolders')
            ->values([
                'folder_id' => $query->createNamedParameter($folderId),
                'mount_point' => $query->createNamedParameter($mountPoint),
                'groups' => $query->createNamedParameter(json_encode($groups)),
                'created_at' => $query->createNamedParameter(time()),
            ])
            ->execute();
    }
    
    /**
     * Set up group permissions for the folder
     *
     * @param int $folderId
     * @param array $groups
     */
    private function setupGroupPermissions($folderId, array $groups) {
        foreach ($groups as $groupId) {
            $group = $this->groupManager->get($groupId);
            if ($group !== null) {
                // Add group permission entry
                $query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
                $query->insert('teamfolders_groups')
                    ->values([
                        'folder_id' => $query->createNamedParameter($folderId),
                        'group_id' => $query->createNamedParameter($groupId),
                        'permissions' => $query->createNamedParameter(31), // All permissions
                    ])
                    ->execute();
            }
        }
    }
    
    /**
     * Get all team folders
     *
     * @return array
     */
    public function getFolders() {
        $query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $query->select('*')
            ->from('teamfolders');
        
        $result = $query->execute();
        $folders = [];
        
        while ($row = $result->fetch()) {
            $folders[] = [
                'folder_id' => $row['folder_id'],
                'mount_point' => $row['mount_point'],
                'groups' => json_decode($row['groups'], true),
                'created_at' => $row['created_at'],
            ];
        }
        
        return $folders;
    }
}
