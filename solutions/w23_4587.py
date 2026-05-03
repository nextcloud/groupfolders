<?php

namespace OCA\TeamFolders\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

class SharingService {
    private $groupManager;
    private $userManager;
    private $shareManager;
    private $rootFolder;
    private $db;
    private $timeFactory;
    private $logger;

    public function __construct(
        IGroupManager $groupManager,
        IUserManager $userManager,
        ShareManager $shareManager,
        IRootFolder $rootFolder,
        IDBConnection $db,
        ITimeFactory $timeFactory,
        LoggerInterface $logger
    ) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->db = $db;
        $this->timeFactory = $timeFactory;
        $this->logger = $logger;
    }

    /**
     * Share a folder with a group, properly handling special characters in group names
     *
     * @param int $folderId
     * @param string $groupName
     * @param int $permissions
     * @return IShare
     * @throws \Exception
     */
    public function shareWithGroup($folderId, $groupName, $permissions) {
        // Sanitize group name to prevent XML parsing issues
        $sanitizedGroupName = $this->sanitizeGroupName($groupName);
        
        // Check if group exists
        $group = $this->groupManager->get($sanitizedGroupName);
        if ($group === null) {
            throw new \InvalidArgumentException('Group not found: ' . $sanitizedGroupName);
        }

        // Get the folder node
        $userFolder = $this->rootFolder->getUserFolder('admin');
        $nodes = $userFolder->getById($folderId);
        if (empty($nodes)) {
            throw new \InvalidArgumentException('Folder not found');
        }
        $node = $nodes[0];

        // Create the share
        $share = $this->shareManager->newShare();
        $share->setNode($node);
        $share->setShareType(IShare::TYPE_GROUP);
        $share->setSharedWith($sanitizedGroupName);
        $share->setPermissions($permissions);
        $share->setShareOwner('admin');
        $share->setSharedBy('admin');
        $share->setShareTime($this->timeFactory->getTime());

        // Create the share
        $share = $this->shareManager->createShare($share);

        // Log the successful share
        $this->logger->info('Shared folder with group: ' . $sanitizedGroupName, [
            'folder_id' => $folderId,
            'permissions' => $permissions
        ]);

        return $share;
    }

    /**
     * Sanitize group name to prevent XML parsing issues
     * 
     * @param string $groupName
     * @return string
     */
    private function sanitizeGroupName($groupName) {
        // Replace & with its HTML entity equivalent
        $sanitized = str_replace('&', '&amp;', $groupName);
        
        // Also handle other special XML characters
        $sanitized = str_replace('<', '&lt;', $sanitized);
        $sanitized = str_replace('>', '&gt;', $sanitized);
        $sanitized = str_replace('"', '&quot;', $sanitized);
        $sanitized = str_replace("'", '&apos;', $sanitized);
        
        return $sanitized;
    }

    /**
     * Get all shares for a folder, properly handling group names with special characters
     *
     * @param int $folderId
     * @return array
     */
    public function getSharesForFolder($folderId) {
        $userFolder = $this->rootFolder->getUserFolder('admin');
        $nodes = $userFolder->getById($folderId);
        if (empty($nodes)) {
            return [];
        }
        $node = $nodes[0];

        $shares = $this->shareManager->getSharesBy('admin', IShare::TYPE_GROUP, $node, true);
        
        // Process shares to ensure group names are properly handled
        $processedShares = [];
        foreach ($shares as $share) {
            $sharedWith = $share->getSharedWith();
            // Decode any HTML entities in the group name
            $decodedGroupName = html_entity_decode($sharedWith, ENT_QUOTES, 'UTF-8');
            
            // Update the share with the decoded group name if needed
            if ($decodedGroupName !== $sharedWith) {
                $share->setSharedWith($decodedGroupName);
            }
            
            $processedShares[] = $share;
        }

        return $processedShares;
    }

    /**
     * Update share permissions for a group, handling special characters
     *
     * @param int $shareId
     * @param int $permissions
     * @return IShare
     * @throws \Exception
     */
    public function updateSharePermissions($shareId, $permissions) {
        $share = $this->shareManager->getShareById($shareId);
        
        // Decode group name if it contains HTML entities
        $sharedWith = $share->getSharedWith();
        $decodedGroupName = html_entity_decode($sharedWith, ENT_QUOTES, 'UTF-8');
        
        if ($decodedGroupName !== $sharedWith) {
            $share->setSharedWith($decodedGroupName);
        }
        
        $share->setPermissions($permissions);
        return $this->shareManager->updateShare($share);
    }

    /**
     * Delete a share, handling special characters in group names
     *
     * @param int $shareId
     * @throws \Exception
     */
    public function deleteShare($shareId) {
        $share = $this->shareManager->getShareById($shareId);
        
        // Decode group name if it contains HTML entities
        $sharedWith = $share->getSharedWith();
        $decodedGroupName = html_entity_decode($sharedWith, ENT_QUOTES, 'UTF-8');
        
        if ($decodedGroupName !== $sharedWith) {
            $share->setSharedWith($decodedGroupName);
        }
        
        $this->shareManager->deleteShare($share);
    }

    /**
     * Get all groups with their display names, properly handling special characters
     *
     * @return array
     */
    public function getAllGroups() {
        $groups = $this->groupManager->search('');
        $groupList = [];
        
        foreach ($groups as $group) {
            $gid = $group->getGID();
            $displayName = $group->getDisplayName();
            
            // Ensure group names are properly encoded for XML/HTML
            $groupList[] = [
                'id' => htmlspecialchars($gid, ENT_QUOTES, 'UTF-8'),
                'displayName' => htmlspecialchars($displayName ?: $gid, ENT_QUOTES, 'UTF-8'),
                'rawId' => $gid,
                'rawDisplayName' => $displayName ?: $gid
            ];
        }
        
        return $groupList;
    }
}
