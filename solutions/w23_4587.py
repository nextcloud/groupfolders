<?php

namespace OCA\TeamFolders\Service;

use OCA\TeamFolders\AppInfo\Application;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class SharingService {
    private $groupManager;
    private $userManager;
    private $shareManager;
    private $rootFolder;
    private $db;
    private $l;
    private $logger;

    public function __construct(
        IGroupManager $groupManager,
        IUserManager $userManager,
        IManager $shareManager,
        IRootFolder $rootFolder,
        IDBConnection $db,
        IL10N $l,
        LoggerInterface $logger
    ) {
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->shareManager = $shareManager;
        $this->rootFolder = $rootFolder;
        $this->db = $db;
        $this->l = $l;
        $this->logger = $logger;
    }

    /**
     * Share a folder with a group, handling special characters in group names
     *
     * @param string $folderId
     * @param string $groupId
     * @param int $permissions
     * @return IShare
     * @throws \Exception
     */
    public function shareWithGroup($folderId, $groupId, $permissions) {
        // Sanitize group name to prevent XML parsing issues
        $sanitizedGroupId = $this->sanitizeGroupName($groupId);
        
        $group = $this->groupManager->get($sanitizedGroupId);
        if ($group === null) {
            throw new \InvalidArgumentException($this->l->t('Group not found'));
        }

        $folder = $this->rootFolder->getById((int)$folderId);
        if (empty($folder)) {
            throw new \InvalidArgumentException($this->l->t('Folder not found'));
        }

        $node = $folder[0];
        
        // Create the share
        $share = $this->shareManager->newShare();
        $share->setNode($node)
            ->setShareType(IShare::TYPE_GROUP)
            ->setSharedWith($sanitizedGroupId)
            ->setPermissions($permissions)
            ->setShareOwner($this->getCurrentUser());

        $share = $this->shareManager->createShare($share);
        
        // Store the original group name mapping if sanitized
        if ($sanitizedGroupId !== $groupId) {
            $this->storeGroupNameMapping($share->getId(), $groupId, $sanitizedGroupId);
        }

        return $share;
    }

    /**
     * Sanitize group name to prevent XML parsing issues
     *
     * @param string $groupName
     * @return string
     */
    private function sanitizeGroupName($groupName) {
        // Replace & with its HTML entity equivalent for XML safety
        $sanitized = str_replace('&', '&amp;', $groupName);
        
        // Also handle other special characters that might cause XML issues
        $sanitized = str_replace('<', '&lt;', $sanitized);
        $sanitized = str_replace('>', '&gt;', $sanitized);
        $sanitized = str_replace('"', '&quot;', $sanitized);
        $sanitized = str_replace("'", '&apos;', $sanitized);
        
        return $sanitized;
    }

    /**
     * Store mapping between original and sanitized group names
     *
     * @param int $shareId
     * @param string $originalName
     * @param string $sanitizedName
     */
    private function storeGroupNameMapping($shareId, $originalName, $sanitizedName) {
        $query = $this->db->getQueryBuilder();
        $query->insert('team_folders_group_mapping')
            ->values([
                'share_id' => $query->createNamedParameter($shareId),
                'original_name' => $query->createNamedParameter($originalName),
                'sanitized_name' => $query->createNamedParameter($sanitizedName),
            ])
            ->execute();
    }

    /**
     * Get the original group name from a sanitized share
     *
     * @param int $shareId
     * @return string|null
     */
    public function getOriginalGroupName($shareId) {
        $query = $this->db->getQueryBuilder();
        $query->select('original_name')
            ->from('team_folders_group_mapping')
            ->where($query->expr()->eq('share_id', $query->createNamedParameter($shareId)));
        
        $result = $query->execute();
        $row = $result->fetch();
        $result->closeCursor();

        return $row ? $row['original_name'] : null;
    }

    /**
     * Get the current user ID
     *
     * @return string
     */
    private function getCurrentUser() {
        $user = \OC::$server->getUserSession()->getUser();
        return $user ? $user->getUID() : '';
    }

    /**
     * Update share permissions for a group
     *
     * @param int $shareId
     * @param int $permissions
     * @return IShare
     * @throws \Exception
     */
    public function updateSharePermissions($shareId, $permissions) {
        $share = $this->shareManager->getShareById($shareId);
        
        // Get original group name if it was sanitized
        $originalName = $this->getOriginalGroupName($shareId);
        if ($originalName !== null) {
            $share->setSharedWith($originalName);
        }
        
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
        
        // Clean up mapping if exists
        $query = $this->db->getQueryBuilder();
        $query->delete('team_folders_group_mapping')
            ->where($query->expr()->eq('share_id', $query->createNamedParameter($shareId)))
            ->execute();
    }
}
