<?php
// Fix for: Creating new team folders does not work if `datadirectory` is not explicitly set
// File: apps/teamfolders/lib/Service/FolderService.php

namespace OCA\TeamFolders\Service;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OCP\Files\Folder;
use OCP\Files\FileInfo;

class FolderService {
    /** @var IRootFolder */
    private $rootFolder;
    
    /** @var IConfig */
    private $config;
    
    /** @var IDBConnection */
    private $db;
    
    /** @var IGroupManager */
    private $groupManager;
    
    /** @var IUserManager */
    private $userManager;
    
    /** @var ShareManager */
    private $shareManager;

    public function __construct(
        IRootFolder $rootFolder,
        IConfig $config,
        IDBConnection $db,
        IGroupManager $groupManager,
        IUserManager $userManager,
        ShareManager $shareManager
    ) {
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->db = $db;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
    }

    /**
     * Create a new team folder
     *
     * @param string $name
     * @param array $groups
     * @param string $mountPoint
     * @return array
     * @throws \Exception
     */
    public function createFolder(string $name, array $groups = [], string $mountPoint = ''): array {
        // Get the data directory from config, with fallback
        $dataDir = $this->config->getSystemValue('datadirectory', '');
        if (empty($dataDir)) {
            // Fallback to default data directory
            $dataDir = \OC::$SERVERROOT . '/data';
        }

        // Ensure the data directory exists
        if (!is_dir($dataDir)) {
            throw new \Exception('Data directory does not exist: ' . $dataDir);
        }

        // Get the admin user (first admin or system user)
        $adminUser = $this->getAdminUser();
        if (!$adminUser) {
            throw new \Exception('No admin user found');
        }

        // Login as admin to create folder
        \OC_Util::setupFS($adminUser->getUID());
        
        try {
            // Get the root folder for the admin user
            $userFolder = $this->rootFolder->getUserFolder($adminUser->getUID());
            
            // Create the team folder in the data directory
            $teamFolderName = $this->sanitizeFolderName($name);
            $teamFolderPath = $dataDir . '/' . $teamFolderName;
            
            if (is_dir($teamFolderPath)) {
                throw new \Exception('Team folder already exists: ' . $teamFolderName);
            }
            
            // Create the physical folder
            if (!mkdir($teamFolderPath, 0770, true)) {
                throw new \Exception('Failed to create team folder directory');
            }
            
            // Create the folder in the filesystem
            $folder = $userFolder->newFolder($teamFolderName);
            
            // Store the folder mapping in database
            $this->storeFolderMapping($folder->getId(), $name, $groups, $mountPoint);
            
            // Set up sharing for specified groups
            if (!empty($groups)) {
                $this->shareFolderWithGroups($folder, $groups);
            }
            
            return [
                'id' => $folder->getId(),
                'name' => $name,
                'path' => $teamFolderPath,
                'groups' => $groups,
                'mount_point' => $mountPoint ?: '/' . $teamFolderName
            ];
            
        } catch (\Exception $e) {
            // Clean up if creation failed
            if (isset($teamFolderPath) && is_dir($teamFolderPath)) {
                rmdir($teamFolderPath);
            }
            throw $e;
        } finally {
            \OC_Util::teardownFS();
        }
    }

    /**
     * Get the admin user for folder creation
     *
     * @return \OCP\IUser|null
     */
    private function getAdminUser(): ?\OCP\IUser {
        // Try to get the first admin user
        $adminGroup = $this->groupManager->get('admin');
        if ($adminGroup) {
            $users = $adminGroup->getUsers();
            if (!empty($users)) {
                return $users[0];
            }
        }
        
        // Fallback to system user
        $uid = $this->config->getSystemValue('instanceid', '');
        if (!empty($uid)) {
            return $this->userManager->get($uid);
        }
        
        return null;
    }

    /**
     * Sanitize folder name
     *
     * @param string $name
     * @return string
     */
    private function sanitizeFolderName(string $name): string {
        // Remove invalid characters
        $name = preg_replace('/[\/:*?"<>|]/', '_', $name);
        // Limit length
        $name = substr($name, 0, 64);
        // Remove leading/trailing dots and spaces
        $name = trim($name, '. ');
        
        if (empty($name)) {
            $name = 'team_folder_' . time();
        }
        
        return $name;
    }

    /**
     * Store folder mapping in database
     *
     * @param int $folderId
     * @param string $name
     * @param array $groups
     * @param string $mountPoint
     */
    private function storeFolderMapping(int $folderId, string $name, array $groups, string $mountPoint): void {
        $query = $this->db->getQueryBuilder();
        $query->insert('team_folders')
            ->values([
                'folder_id' => $query->createNamedParameter($folderId),
                'name' => $query->createNamedParameter($name),
                'groups' => $query->createNamedParameter(json_encode($groups)),
                'mount_point' => $query->createNamedParameter($mountPoint),
                'created_at' => $query->createNamedParameter(time())
            ]);
        $query->execute();
    }

    /**
     * Share folder with specified groups
     *
     * @param Folder $folder
     * @param array $groups
     */
    private function shareFolderWithGroups(Folder $folder, array $groups): void {
        foreach ($groups as $groupId) {
            $group = $this->groupManager->get($groupId);
            if ($group) {
                $share = $this->shareManager->newShare();
                $share->setNode($folder)
                    ->setShareType(\OCP\Share::SHARE_TYPE_GROUP)
                    ->setSharedWith($groupId)
                    ->setPermissions(\OCP\Constants::PERMISSION_ALL);
                
                $this->shareManager->createShare($share);
            }
        }
    }

    /**
     * Get all team folders
     *
     * @return array
     */
    public function getAllFolders(): array {
        $query = $this->db->getQueryBuilder();
        $query->select('*')
            ->from('team_folders');
        
        $result = $query->execute();
        $folders = [];
        
        while ($row = $result->fetch()) {
            $folders[] = [
                'id' => $row['folder_id'],
                'name' => $row['name'],
                'groups' => json_decode($row['groups'], true) ?: [],
                'mount_point' => $row['mount_point'],
                'created_at' => $row['created_at']
            ];
        }
        
        return $folders;
    }
}
