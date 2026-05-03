<?php

namespace OCA\TeamFolders\Service;

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager;
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
        IManager $shareManager,
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
     * Share a folder with a group, handling special characters in group names
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
            throw new \Exception('Group not found: ' . $sanitizedGroupName);
        }

        // Get the folder node
        $userFolder = $this->rootFolder->getUserFolder('admin');
        $nodes = $userFolder->getById($folderId);
        if (empty($nodes)) {
            throw new \Exception('Folder not found');
        }
        $node = $nodes[0];

        // Create share
        $share = $this->shareManager->newShare();
        $share->setNode($node);
        $share->setShareType(IShare::TYPE_GROUP);
        $share->setSharedWith($sanitizedGroupName);
        $share->setPermissions($permissions);
        $share->setShareOwner('admin');
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
        // Replace & with its XML entity equivalent
        $sanitized = str_replace('&', '&amp;', $groupName);
        
        // Also handle other special XML characters
        $sanitized = str_replace('<', '&lt;', $sanitized);
        $sanitized = str_replace('>', '&gt;', $sanitized);
        $sanitized = str_replace('"', '&quot;', $sanitized);
        $sanitized = str_replace("'", '&apos;', $sanitized);
        
        return $sanitized;
    }

    /**
     * Get all shares for a folder
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

        $shares = $this->shareManager->getSharesByNode($node);
        return $shares;
    }

    /**
     * Update share permissions
     *
     * @param int $shareId
     * @param int $permissions
     * @return IShare
     */
    public function updateSharePermissions($shareId, $permissions) {
        $share = $this->shareManager->getShareById($shareId);
        $share->setPermissions($permissions);
        return $this->shareManager->updateShare($share);
    }

    /**
     * Delete a share
     *
     * @param int $shareId
     */
    public function deleteShare($shareId) {
        $share = $this->shareManager->getShareById($shareId);
        $this->shareManager->deleteShare($share);
    }

    /**
     * Get group members for display
     *
     * @param string $groupName
     * @return array
     */
    public function getGroupMembers($groupName) {
        $sanitizedGroupName = $this->sanitizeGroupName($groupName);
        $group = $this->groupManager->get($sanitizedGroupName);
        if ($group === null) {
            return [];
        }

        $users = $group->getUsers();
        $members = [];
        foreach ($users as $user) {
            $members[] = [
                'uid' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
            ];
        }
        return $members;
    }
}
