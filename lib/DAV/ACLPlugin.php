<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019-2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\DAV;

use OCA\DAV\Connector\Sabre\Node;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\Xml\Reader;

/**
 * SabreDAV plugin for exposing ACL (advanced permissions) properties.
 *
 * Handles WebDAV PROPFIND and PROPPATCH events for Nextcloud Teams/Group Folders with granular access controls.
 *
 * These handlers:
 * - Ensures only relevant information is returned/modifiable for the target node.
 * - Support both admin and user-level requests.
 *
 * Admins have a full overview and control:
 * - can see and manage all inherited permission entries.
 * - can see and manage rules for other users/groups.
 *
 * Standard users see only their own effective inherited permissions:
 * - only see inherited permissions that affect them specifically.
 * - can't view or manage rules for other users/groups.
 */
class ACLPlugin extends ServerPlugin {
	public const ACL_ENABLED = '{http://nextcloud.org/ns}acl-enabled';
	public const ACL_CAN_MANAGE = '{http://nextcloud.org/ns}acl-can-manage';
	public const ACL_LIST = '{http://nextcloud.org/ns}acl-list';
	public const INHERITED_ACL_LIST = '{http://nextcloud.org/ns}inherited-acl-list';
	public const GROUP_FOLDER_ID = '{http://nextcloud.org/ns}group-folder-id';
	public const ACL_BASE_PERMISSION_PROPERTYNAME = '{http://nextcloud.org/ns}acl-base-permission';

	private ?Server $server = null;
	private ?IUser $user = null;
	/** @var array<int, bool> Folder ID => can manage ACLs */
	private array $canManageACLForFolder = [];

	public function __construct(
		private readonly RuleManager $ruleManager,
		private readonly IUserSession $userSession,
		private readonly FolderManager $folderManager,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly ACLManagerFactory $aclManagerFactory,
		private readonly IL10N $l10n,
	) {
	}

	public function initialize(Server $server): void {
		$this->server = $server;

		// Note: a null user is permitted (i.e. for public links / federated shares); handler logic must account for this.
		$this->user = $this->userSession->getUser();

		$this->server->on('propFind', $this->propFind(...));
		$this->server->on('propPatch', $this->propPatch(...));

		$this->server->xml->elementMap[Rule::ACL]
			= Rule::class;
		$this->server->xml->elementMap[self::ACL_LIST]
			= fn (Reader $reader): array
				=> \Sabre\Xml\Deserializer\repeatingElements($reader, Rule::ACL);
	}

	/**
	 * WebDAV PROPFIND event handler for ACL-related properties.
	 * Provides read-only access to ACL information for the current node.
	 * If the session is unauthenticated, safe defaults are returned.
	 */
	public function propFind(PropFind $propFind, INode $node): void {
		if (!$node instanceof Node) {
			return;
		}

		$fileInfo = $node->getFileInfo();
		$mount = $fileInfo->getMountPoint();
		if (!$mount instanceof GroupMountPoint) {
			return;
		}

		// Handler to provide ACL rules directly assigned to the file or folder.
		$propFind->handle(
			self::ACL_LIST,
			fn () => $this->getDirectAclRulesForPath($fileInfo, $mount)
		);

		// Handler to provide the ACL rules inherited from parent folders (not set directly).
		$propFind->handle(
			self::INHERITED_ACL_LIST,
			fn () => $this->getInheritedAclRulesForPath($fileInfo, $mount)
		);

		// Handler to provide the group folder ID for the current file or folder.
		$propFind->handle(
			self::GROUP_FOLDER_ID,
			fn (): int => $this->folderManager->getFolderByPath($fileInfo->getPath())
		);

		// Handler to provide whether ACLs are enabled for the current group folder.
		$propFind->handle(
			self::ACL_ENABLED,
			function () use ($fileInfo): bool {
				$folderId = $this->folderManager->getFolderByPath($fileInfo->getPath());
				return $this->folderManager->getFolderAclEnabled($folderId);
			}
		);

		// Handler to determine and return if the current user can manage ACLs for this group folder.
		$propFind->handle(
			self::ACL_CAN_MANAGE,
			function () use ($fileInfo): bool {
				// Gracefully handle non-user sessions
				if ($this->user === null) {
					return false;
				}
				return $this->isAdmin($this->user, $fileInfo->getPath());
			}
		);

		// Handler to provide the effective base permissions for the current group folder.
		$propFind->handle(
			self::ACL_BASE_PERMISSION_PROPERTYNAME,
			function () use ($mount): int {
				// Gracefully handle non-user sessions
				if ($this->user === null) {
					return Constants::PERMISSION_ALL;
				}
				return $this->aclManagerFactory
					->getACLManager($this->user)
					->getBasePermission($mount->getFolderId());
			}
		);
	}

	/**
	 * WebDAV PROPPATCH event handler for ACL-related properties.
	 * Enables modification of ACL assignments if the user has admin rights on the current node.
	 */
	public function propPatch(string $path, PropPatch $propPatch): void {
		if ($this->server === null) {
			return;
		}

		// Non-user sessions (public link or federated share); no update handling is supported.
		if ($this->user === null) {
			return;
		}

		$node = $this->server->tree->getNodeForPath($path);

		if (!$node instanceof Node) {
			return;
		}

		$fileInfo = $node->getFileInfo();
		$mount = $fileInfo->getMountPoint();

		if (!$mount instanceof GroupMountPoint) {
			return;
		}

		// Only allow if user has admin rights for this group folder
		if (!$this->isAdmin($this->user, $fileInfo->getPath())) {
			return;
		}

		// Handler to update (replace) the direct ACL rules for the specified file/folder.
		$propPatch->handle(
			self::ACL_LIST,
			fn (array $submittedRules) => $this->updateAclRulesForPath($submittedRules, $node, $fileInfo, $mount)
		);
	}

	/**
	 * Retrieves ACL rules assigned directly (not inherited) to the given file or folder.
	 *
	 * - For admins/managers: returns all direct rules for the node.
	 * - For non-admins/users: returns only direct rules relevant to the user.
	 * - For public/federated sessions: returns an empty array.
	 *
	 * Example: Granting "Group X" write access to `/Documents/Reports` is a direct ACL rule for that folder.
	 *
	 * Note: Direct rules alone do not determine effective access:
	 * - Read/list access must be permitted by all parent folders, regardless of direct rules.
	 * - For other permissions, direct rules take priority; missing permissions may be filled via inheritance.
	 *
	 * Example: If "Group X" has no read access on `/Documents`, they still can't access `/Documents/Reports`.
	 *
	 * @return list<Rule> Direct ACL rules for the file/folder (may be empty).
	 */
	private function getDirectAclRulesForPath(FileInfo $fileInfo, GroupMountPoint $mount): array {
		if ($this->user === null) {
			return [];
		}

		$aclRelativePath = trim($mount->getSourcePath() . '/' . $fileInfo->getInternalPath(), '/');

		// Retrieve the direct rules
		if ($this->isAdmin($this->user, $fileInfo->getPath())) {
			// Admin
			$rules = $this->ruleManager->getAllRulesForPaths(
				$mount->getNumericStorageId(),
				[$aclRelativePath]
			);
		} else {
			// Standard user
			$rules = $this->ruleManager->getRulesForFilesByPath(
				$this->user,
				$mount->getNumericStorageId(),
				[$aclRelativePath]
			);
		}

		// Return the rules for the requested path (only one path is queried, so take the single result)
		return array_values(array_pop($rules) ?? []);
	}

	/**
	 * Retrieves ACL rules inherited from parent folders (not set directly) for the given file or folder.
	 *
	 * - For admins/managers: returns all inherited rules affecting the node.
	 * - For non-admins/users: returns only inherited rules relevant to the user.
	 * - For public/federated sessions: returns an empty array.
	 *
	 * Note: Inherited rules are merged from all ancestor folders; direct rules for this node are excluded.
	 * - Effective read/list access requires all parent folders to permit access.
	 * - Other permissions are determined by merging inherited and direct rules separately.
	 *
	 * @return list<Rule> Inherited ACL rules affecting the file/folder (may be empty).
	 */
	private function getInheritedAclRulesForPath(FileInfo $fileInfo, GroupMountPoint $mount): array {
		// Fail softly for non-user sessions
		if ($this->user === null) {
			return [];
		}

		$parentInternalPaths = $this->getParents($fileInfo->getInternalPath());
		$parentAclRelativePaths = array_map(
			fn (string $internalPath): string
				=> trim($mount->getSourcePath() . '/' . $internalPath, '/'),
			$parentInternalPaths
		);
		// Include the mount root
		$parentAclRelativePaths[] = $mount->getSourcePath();

		// Retrieve the inherited rules
		if ($this->isAdmin($this->user, $fileInfo->getPath())) {
			// Admin
			$rulesByPath = $this->ruleManager->getAllRulesForPaths(
				$mount->getNumericStorageId(),
				$parentAclRelativePaths
			);
		} else {
			// Standard user
			$rulesByPath = $this->ruleManager->getRulesForFilesByPath(
				$this->user,
				$mount->getNumericStorageId(),
				$parentAclRelativePaths
			);
		}

		/*
		 * Aggregate inherited permissions for each relevant user/group/team across all parent paths.
		 *
		 * For each mapping (identified by type + ID):
		 * - Initialize the mapping if it hasn't been seen yet.
		 * - Accumulate permissions by applying each parent rule in order
		 *   (to correctly resolve permissions as they cascade from ancestor to descendant).
		 * - Bitwise-OR the masks to track all inherited permission bits.
		 */
		ksort($rulesByPath);					// Ensure parent paths are applied from root down
		$inheritedPermissionsByUserKey = [];	// Effective permissions per mapping
		$inheritedMaskByUserKey = [];			// Combined permission masks per mapping
		$userMappingsByKey = [];				// Mapping reference for later rule creation
		$aclManager = $this->aclManagerFactory->getACLManager($this->user);

		foreach ($rulesByPath as $rules) {
			foreach ($rules as $rule) {
				// Create a unique key for each user/group/team mapping
				$userMappingKey = $rule->getUserMapping()->getType() . '::' . $rule->getUserMapping()->getId();

				// Store mapping object if first encounter
				if (!isset($userMappingsByKey[$userMappingKey])) {
					$userMappingsByKey[$userMappingKey] = $rule->getUserMapping();
				}

				// Initialize inherited permissions if not set
				if (!isset($inheritedPermissionsByUserKey[$userMappingKey])) {
					$inheritedPermissionsByUserKey[$userMappingKey] = $aclManager->getBasePermission($mount->getFolderId());
				}

				// Initialize mask if not set
				if (!isset($inheritedMaskByUserKey[$userMappingKey])) {
					$inheritedMaskByUserKey[$userMappingKey] = 0;
				}

				// Apply rule's permissions to current inherited permissions
				$inheritedPermissionsByUserKey[$userMappingKey] = $rule->applyPermissions($inheritedPermissionsByUserKey[$userMappingKey]);

				// Accumulate mask bits
				$inheritedMaskByUserKey[$userMappingKey] |= $rule->getMask();
			}
		}

		$fileId = $fileInfo->getId();
		if ($fileId === null) {
			// shouldn't ever happen (only part files can return null)
			throw new \LogicException('File ID cannot be null');
		}

		// Build and return Rule objects representing the effective inherited permissions for each mapping
		return array_map(
			fn (IUserMapping $mapping, int $permissions, int $mask): Rule => new Rule(
				$mapping,
				$fileId,
				$mask,
				$permissions
			),
			$userMappingsByKey,
			$inheritedPermissionsByUserKey,
			$inheritedMaskByUserKey
		);
	}

	/**
	 * Update (replace) the entire set of direct ACL rules for the given file or folder.
	 *
	 * This method overwrites all existing direct ACL rules for the node with a new set.
	 * Only users with ACL management rights (admins/managers) can perform this operation.
	 *
	 * - All provided Rule objects are written as the new direct ACLs for this file/folder.
	 * - Inherited ACL rules from parent folders are not modified.
	 * - If the rules array is empty, all direct ACLs on this node are removed.
	 * - Logs critical actions and dispatches audit events for ACL changes.
	 *
	 * @param Rule[] $submittedRules Array of new direct ACL Rule objects to apply.
	 * @param $node object of file/folder being updated.
	 * @param FileInfo $fileInfo object of file or folder being updated.
	 * @param GroupMountPoint $mount context for storage and folder resolution.
	 *
	 * @throws BadRequest if the operation is invalid (e.g. user's own read access would be removed)
	 */
	private function updateAclRulesForPath(array $submittedRules, Node $node, FileInfo $fileInfo, GroupMountPoint $mount): bool {
		$aclRelativePath = trim($mount->getSourcePath() . '/' . $fileInfo->getInternalPath(), '/');

		$fileId = $fileInfo->getId();
		if ($fileId === null) {
			// shouldn't ever happen (only part files can return null)
			throw new \LogicException('File ID cannot be null');
		}

		// Make sure each submitted rule is associated with the current file's ID
		$preparedRules = array_values(
			array_map(
				fn (Rule $rule): Rule => new Rule(
					$rule->getUserMapping(),
					$fileId,
					$rule->getMask(),
					$rule->getPermissions()
				),
				$submittedRules
			)
		);

		// Generate a display-friendly description string for each rule
		$rulesDescriptions = array_map(
			fn (Rule $rule): string
				=> $rule->getUserMapping()->getType()
				. ' '
				. $rule->getUserMapping()->getDisplayName()
				. ': ' . $rule->formatPermissions(),
			$preparedRules
		);

		// Record changes in the audit log
		if (count($rulesDescriptions)) {
			$rulesDescriptionsStr = implode(', ', $rulesDescriptions);
			$this->eventDispatcher->dispatchTyped(
				new CriticalActionPerformedEvent(
					'The advanced permissions for "%s" in Team folder with ID %d was set to "%s"',
					[ $fileInfo->getInternalPath(), $mount->getFolderId(), $rulesDescriptionsStr ]
				)
			);
		} else {
			$this->eventDispatcher->dispatchTyped(
				new CriticalActionPerformedEvent(
					'The advanced permissions for "%s" in Team folder with ID %d was cleared',
					[ $fileInfo->getInternalPath(), $mount->getFolderId() ]
				)
			);
		}

		// Simulate new ACL rules to ensure the user does not remove their own read access before saving changes
		/** @psalm-suppress PossiblyNullArgument already checked by caller */
		$aclManager = $this->aclManagerFactory->getACLManager($this->user);
		$newPermissions = $aclManager->testACLPermissionsForPath(
			$mount->getFolderId(),
			$mount->getNumericStorageId(),
			$aclRelativePath,
			$preparedRules
		);
		if (!($newPermissions & Constants::PERMISSION_READ)) {
			throw new BadRequest($this->l10n->t('You cannot remove your own read permission.'));
		}

		// Compute all existing ACL rules associated with the file path
		$existingRules = array_reduce(
			$this->ruleManager->getAllRulesForPaths(
				$mount->getNumericStorageId(),
				[$aclRelativePath]
			),
			array_merge(...),
			[]
		);

		// If a mapping is missing in the new set, it means its rule should be deleted, regardless of its old permissions.
		$rulesToDelete = array_udiff(
			$existingRules,
			$preparedRules,
			fn (Rule $existingRule, Rule $submittedRule): int => (
				// Only compare by mapping (type + ID) since all rules here are already contextual to the same path.
				($existingRule->getUserMapping()->getType() <=> $submittedRule->getUserMapping()->getType())
				?: ($existingRule->getUserMapping()->getId() <=> $submittedRule->getUserMapping()->getId())
			)
		);

		// Delete no longer present rules
		foreach ($rulesToDelete as $ruleToDelete) {
			$this->ruleManager->deleteRule($ruleToDelete);
		}

		// Save new rules
		foreach ($preparedRules as $rule) {
			$this->ruleManager->saveRule($rule);
		}

		// Propagate changes to file cache
		$node->getNode()
			->getStorage()
			->getPropagator()
			->propagateChange(
				$fileInfo->getInternalPath(),
				$fileInfo->getMtime()
			);

		return true;
	}

	/**
	 * Checks if a user has admin (ACL management) rights for the group folder at the provided path.
	 * Results are cached per folder for efficiency.
	 *
	 * @param IUser $user The user to check.
	 * @param string $path The full path to a file or folder inside a group folder.
	 *
	 * @throws \OCP\Files\NotFoundException If the path does not exist or is not part of a group folder.
	 */
	private function isAdmin(IUser $user, string $path): bool {
		// TODO: catch/handle gracefully if folder disappeared between node fetch and this check (i.e. by another user / session)
		$folderId = $this->folderManager->getFolderByPath($path);

		if (isset($this->canManageACLForFolder[$folderId])) {
			return $this->canManageACLForFolder[$folderId];
		}

		$canManage = $this->folderManager->canManageACL($folderId, $user);
		$this->canManageACLForFolder[$folderId] = $canManage;
		return $canManage;
	}

	/**
	 * Returns all parent directory paths of the given path, from nearest to root.
	 * Excludes the original path itself.
	 * E.g.: 'a/b/c.txt' → ['a/b', 'a']
	 *
	 * @param string $path Path to a file or directory.
	 * @return list<string> Parent directory paths, from closest to furthest.
	 */
	private function getParents(string $path): array {
		$parents = [];
		$parent = dirname($path);
		while ($parent !== '' && $parent !== '.' && $parent !== '/') {
			$parents[] = $parent;
			$parent = dirname($parent);
		}

		return $parents;
	}
}
