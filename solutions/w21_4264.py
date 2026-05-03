<?php
// Fix for: Creating new team folders does not work if `datadirectory` is not explicitly set
// File: apps/teamfolders/lib/Service/FolderService.php

namespace OCA\TeamFolders\Service;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Share\IManager as ShareManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCA\TeamFolders\Db\FolderMapper;
use OCA\TeamFolders\Db\Folder;
use OCA\TeamFolders\Mount\MountProvider;
use Psr\Log\LoggerInterface;

class FolderService {
    /** @var IRootFolder */
    private $rootFolder;
    
    /** @var IConfig */
    private $config;
    
    /** @var IUserManager */
    private $userManager;
    
    /** @var IGroupManager */
    private $groupManager;
    
    /** @var ShareManager */
    private $shareManager;
    
    /** @var ITimeFactory */
    private $timeFactory;
    
    /** @var FolderMapper */
    private $folderMapper;
    
    /** @var MountProvider */
    private $mountProvider;
    
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        IRootFolder $rootFolder,
        IConfig $config,
        IUserManager $userManager,
        IGroupManager $groupManager,
        ShareManager $shareManager,
        ITimeFactory $timeFactory,
        FolderMapper $folderMapper,
        MountProvider $mountProvider,
        LoggerInterface $logger
    ) {
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->shareManager = $shareManager;
        $this->timeFactory = $timeFactory;
        $this->folderMapper = $folderMapper;
        $this->mountProvider = $mountProvider;
        $this->logger = $logger;
    }

    /**
     * Create a new team folder
     *
     * @param string $name
     * @param array $groups
     * @param int $quota
     * @return Folder
     * @throws \Exception
     */
    public function create(string $name, array $groups = [], int $quota = -3): Folder {
        $this->logger->info('Creating team folder: ' . $name);
        
        // Get the data directory - FIX: use getSystemValue with default
        $dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
        
        // Ensure data directory exists
        if (!is_dir($dataDir)) {
            throw new \Exception('Data directory does not exist: ' . $dataDir);
        }
        
        // Create the folder in the filesystem
        $teamFoldersDir = $dataDir . '/teamfolders';
        if (!is_dir($teamFoldersDir)) {
            if (!mkdir($teamFoldersDir, 0770, true)) {
                throw new \Exception('Could not create team folders directory');
            }
        }
        
        // Generate unique folder ID
        $folderId = uniqid('tf_', true);
        $folderPath = $teamFoldersDir . '/' . $folderId;
        
        // Create the actual folder
        if (!mkdir($folderPath, 0770, true)) {
            throw new \Exception('Could not create team folder: ' . $folderPath);
        }
        
        // Create database entry
        $folder = new Folder();
        $folder->setName($name);
        $folder->setFolderId($folderId);
        $folder->setQuota($quota);
        $folder->setCreatedAt($this->timeFactory->getTime());
        $folder->setUpdatedAt($this->timeFactory->getTime());
        
        // Save to database
        $folder = $this->folderMapper->insert($folder);
        
        // Add groups if specified
        if (!empty($groups)) {
            foreach ($groups as $groupId => $permissions) {
                $this->addGroup($folder->getId(), $groupId, $permissions);
            }
        }
        
        // Clear mount cache to ensure new folder is visible
        $this->mountProvider->clearCache();
        
        $this->logger->info('Team folder created successfully: ' . $folder->getId());
        
        return $folder;
    }

    /**
     * Add a group to a team folder
     *
     * @param int $folderId
     * @param string $groupId
     * @param int $permissions
     * @throws \Exception
     */
    public function addGroup(int $folderId, string $groupId, int $permissions = 31): void {
        $folder = $this->folderMapper->find($folderId);
        if (!$folder) {
            throw new NotFoundException('Folder not found');
        }
        
        // Check if group exists
        $group = $this->groupManager->get($groupId);
        if (!$group) {
            throw new \Exception('Group not found: ' . $groupId);
        }
        
        // Add group to folder
        $this->folderMapper->addGroup($folderId, $groupId, $permissions);
        
        // Update mount points for all group members
        $users = $group->getUsers();
        foreach ($users as $user) {
            $this->mountProvider->addMountForUser($user->getUID(), $folder);
        }
    }

    /**
     * Delete a team folder
     *
     * @param int $folderId
     * @throws \Exception
     */
    public function delete(int $folderId): void {
        $folder = $this->folderMapper->find($folderId);
        if (!$folder) {
            throw new NotFoundException('Folder not found');
        }
        
        // Get data directory
        $dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
        $folderPath = $dataDir . '/teamfolders/' . $folder->getFolderId();
        
        // Remove physical folder
        if (is_dir($folderPath)) {
            $this->removeDirectory($folderPath);
        }
        
        // Remove from database
        $this->folderMapper->delete($folder);
        
        // Clear cache
        $this->mountProvider->clearCache();
    }

    /**
     * Recursively remove a directory
     *
     * @param string $dir
     */
    private function removeDirectory(string $dir): void {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
