<?php
// Fix for: Creating new team folders does not work if `datadirectory` is not explicitly set
// File: apps/teamfolders/lib/Service/FolderService.php

namespace OCA\TeamFolders\Service;

use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IL10N;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\PreConditionNotMetException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCA\TeamFolders\Db\FolderMapper;
use OCA\TeamFolders\Db\Folder;
use OCA\TeamFolders\Mount\MountProvider;
use OCA\TeamFolders\Service\FolderService as BaseFolderService;

class FolderService extends BaseFolderService {
    
    /** @var IConfig */
    private $config;
    
    /** @var IRootFolder */
    private $rootFolder;
    
    /** @var FolderMapper */
    private $folderMapper;
    
    /** @var MountProvider */
    private $mountProvider;
    
    public function __construct(
        IRootFolder $rootFolder,
        IConfig $config,
        IGroupManager $groupManager,
        IUserManager $userManager,
        IL10N $l10n,
        FolderMapper $folderMapper,
        MountProvider $mountProvider
    ) {
        parent::__construct($rootFolder, $config, $groupManager, $userManager, $l10n, $folderMapper, $mountProvider);
        $this->config = $config;
        $this->rootFolder = $rootFolder;
        $this->folderMapper = $folderMapper;
        $this->mountProvider = $mountProvider;
    }
    
    /**
     * Create a new team folder
     *
     * @param string $mountPoint
     * @param array $groups
     * @param array $quota
     * @return Folder
     * @throws \Exception
     */
    public function create($mountPoint, $groups = [], $quota = []) {
        // Get the data directory from config, with fallback
        $dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
        
        // Ensure we have a valid data directory
        if (empty($dataDir)) {
            $dataDir = \OC::$SERVERROOT . '/data';
        }
        
        // Normalize the path
        $dataDir = rtrim($dataDir, '/');
        
        // Create the team folders directory if it doesn't exist
        $teamFoldersDir = $dataDir . '/teamfolders';
        if (!is_dir($teamFoldersDir)) {
            if (!@mkdir($teamFoldersDir, 0770, true)) {
                throw new \Exception('Failed to create team folders directory: ' . $teamFoldersDir);
            }
        }
        
        // Generate a unique folder ID
        $folderId = uniqid('teamfolder_', true);
        $folderPath = $teamFoldersDir . '/' . $folderId;
        
        // Create the actual folder
        if (!@mkdir($folderPath, 0770, true)) {
            throw new \Exception('Failed to create team folder: ' . $folderPath);
        }
        
        // Create the database entry
        $folder = new Folder();
        $folder->setMountPoint($mountPoint);
        $folder->setFolderId($folderId);
        $folder->setQuota(isset($quota['quota']) ? (int)$quota['quota'] : -3); // -3 = unlimited
        $folder->setAclEnabled(false);
        
        try {
            $folder = $this->folderMapper->insert($folder);
        } catch (\Exception $e) {
            // Clean up the created folder if DB insert fails
            if (is_dir($folderPath)) {
                @rmdir($folderPath);
            }
            throw $e;
        }
        
        // Add groups to the folder
        if (!empty($groups)) {
            foreach ($groups as $group) {
                $this->addGroup($folder->getId(), $group['gid'], $group['permissions'] ?? \OCP\Constants::PERMISSION_ALL);
            }
        }
        
        // Clear the mount provider cache
        $this->mountProvider->clearCache();
        
        return $folder;
    }
    
    /**
     * Get the data directory path
     *
     * @return string
     */
    private function getDataDirectory() {
        $dataDir = $this->config->getSystemValue('datadirectory', '');
        
        if (empty($dataDir)) {
            // Fallback to default Nextcloud data directory
            $dataDir = \OC::$SERVERROOT . '/data';
        }
        
        return rtrim($dataDir, '/');
    }
}
