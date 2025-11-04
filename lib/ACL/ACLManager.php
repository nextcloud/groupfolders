<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Cache\CappedMemoryCache;
use OCP\Constants;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Server;

class ACLManager {
	/** @var CappedMemoryCache<Rule[]> */
	private readonly CappedMemoryCache $ruleCache;

	public function __construct(
		private readonly RuleManager $ruleManager,
		private readonly IUserMappingManager $userMappingManager,
		private readonly IUser $user,
		private readonly bool $inheritMergePerUser = false,
	) {
		$this->ruleCache = new CappedMemoryCache();
	}

	/**
	 * Get the list of rules applicable for a set of paths
	 *
	 * @param string[] $paths
	 * @param bool $cache whether to cache the retrieved rules
	 * @return array<string, Rule[]> sorted parent first
	 */
	private function getRules(int $storageId, array $paths, bool $cache = true): array {
		// beware: adding new rules to the cache besides the cap
		// might discard former cached entries, so we can't assume they'll stay
		// cached, so we read everything out initially to be able to return it
		/** @var array<string, Rule[]> $rules */
		$rules = array_combine($paths, array_map($this->ruleCache->get(...), $paths));

		$nonCachedPaths = array_filter($paths, fn (string $path): bool => !isset($rules[$path]));

		if (!empty($nonCachedPaths)) {
			$newRules = $this->ruleManager->getRulesForFilesByPath($this->user, $storageId, $nonCachedPaths);
			foreach ($newRules as $path => $rulesForPath) {
				if ($cache) {
					$this->ruleCache->set($path, $rulesForPath);
				}

				$rules[$path] = $rulesForPath;
			}
		}

		ksort($rules);

		return $rules;
	}

	/**
	 * Get the list of rules applicable for a set of paths
	 *
	 * @param int[] $fileIds
	 * @param bool $cache whether to cache the retrieved rules
	 * @return array<string, Rule[]> sorted parent first
	 */
	public function getRulesByFileIds(array $fileIds, bool $cache = true): array {
		$rules = [];

		$newRules = $this->ruleManager->getRulesForFilesByIds($this->user, $fileIds);
		foreach ($newRules as $path => $rulesForPath) {
			if ($cache) {
				$this->ruleCache->set($path, $rulesForPath);
			}

			$rules[$path] = $rulesForPath;
		}

		ksort($rules);

		return $rules;
	}

	/**
	 * Get a list of all path that might contain relevant rules when calculating the permissions for a path
	 *
	 * This contains the $path itself and any parent folder
	 *
	 * @return string[]
	 */
	private function getRelevantPaths(string $path, string $basePath = ''): array {
		$paths = [];
		while ($path !== '') {
			$paths[] = $path;
			$path = dirname($path);

			if ($path === '.' || $path === '/') {
				$path = '';
			}

			if ($path === $basePath) {
				break;
			}
		}

		return $paths;
	}

	/**
	 * Get the list of rules applicable for a set of paths, including rules for any parent
	 *
	 * @param string[] $paths
	 * @param bool $cache whether to cache the retrieved rules
	 * @return array<string, Rule[]> sorted parent first
	 */
	public function getRelevantRulesForPath(int $storageId, array $paths, bool $cache = true): array {
		$allPaths = [];
		foreach ($paths as $path) {
			$allPaths = array_unique(array_merge($allPaths, $this->getRelevantPaths($path)));
		}

		return $this->getRules($storageId, $allPaths, $cache);
	}

	public function getACLPermissionsForPath(int $folderId, int $storageId, string $path, string $basePath = ''): int {
		$path = ltrim($path, '/');
		$rules = $this->getRules($storageId, $this->getRelevantPaths($path, $basePath));

		return $this->calculatePermissionsForPath($folderId, $rules);
	}

	/**
	 * Check what the effective permissions would be for the current user for a path would be with a new set of rules
	 *
	 * @param list<Rule> $newRules
	 */
	public function testACLPermissionsForPath(int $folderId, int $storageId, string $path, array $newRules): int {
		$path = ltrim($path, '/');
		$rules = $this->getRules($storageId, $this->getRelevantPaths($path));

		$rules[$path] = $this->filterApplicableRulesToUser($newRules);

		return $this->calculatePermissionsForPath($folderId, $rules);
	}

	/**
	 * @param array<string, Rule[]> $rules list of rules per path
	 */
	public function getPermissionsForPathFromRules(int $folderId, string $path, array $rules): int {
		$path = ltrim($path, '/');
		$relevantPaths = $this->getRelevantPaths($path);
		$rules = array_intersect_key($rules, array_flip($relevantPaths));

		return $this->calculatePermissionsForPath($folderId, $rules);
	}

	/**
	 * @param array<string, Rule[]> $rules list of rules per path, sorted parent first
	 */
	private function calculatePermissionsForPath(int $folderId, array $rules): int {
		// given the following rules
		//
		// | Folder Rule | Read | Update | Share | Delete |
		// |-------------|------|--------|-------|--------|
		// | a: g1       | 1    | 1      | 1     | 1      |
		// | a: g2       | -    | -      | -     | -      |
		// | a/b: g1     | -    | -      | -     | 0      |
		// | a/b: g2     | 0    | -      | -     | -      |
		// |-------------|------|--------|-------|--------|
		//
		// and a user that is a member of g1 and g2
		//
		// Without `inheritMergePerUser` the user will not have access to `a/b`
		// as the merged rules for `a/b` ("-read,-delete") will overwrite the merged for `a` ("+read,+write+share+delete")
		//
		// With b`inheritMergePerUser` the user will have access to `a/b`
		// as the applied rules for `g1` ("+read,+write+share") merges with the applied rules for `g2` ("-read")
		if ($this->inheritMergePerUser) {
			// first combine all rules for the same user-mapping by path order
			// then merge the results with allow overwrites deny
			$rulesPerMapping = [];
			foreach ($rules as $rulesForPath) {
				foreach ($rulesForPath as $rule) {
					$mapping = $rule->getUserMapping();
					$key = $mapping->getType() . '/' . $mapping->getId();
					if (!isset($rulesPerMapping[$key])) {
						$rulesPerMapping[$key] = Rule::defaultRule();
					}

					$rulesPerMapping[$key]->applyRule($rule);
				}
			}

			$mergedRule = Rule::mergeRules($rulesPerMapping);

			return $mergedRule->applyPermissions($this->getBasePermission($folderId));
		} else {
			// first combine all rules with the same path, then apply them on top of the current permissions
			// since $rules is sorted parent first rules for subfolders overwrite the rules from the parent
			return array_reduce($rules, function (int $permissions, array $rules): int {
				$mergedRule = Rule::mergeRules($rules);
				return $mergedRule->applyPermissions($permissions);
			}, $this->getBasePermission($folderId));
		}
	}

	/**
	 * Get the combined "lowest" permissions for an entire directory tree
	 */
	public function getPermissionsForTree(int $folderId, int $storageId, string $path): int {
		$path = ltrim($path, '/');
		$rules = $this->ruleManager->getRulesForPrefix($this->user, $storageId, $path);

		if ($this->inheritMergePerUser) {
			$pathsWithRules = array_keys($rules);
			$permissions = $this->getBasePermission($folderId);
			foreach ($pathsWithRules as $path) {
				$permissions &= $this->getACLPermissionsForPath($folderId, $storageId, $path);
			}
			return $permissions;
		} else {
			return array_reduce($rules, function (int $permissions, array $rules): int {
				$mergedRule = Rule::mergeRules($rules);
				return $mergedRule->applyDenyPermissions($permissions);
			}, $this->getBasePermission($folderId));
		}
	}

	public function preloadRulesForFolder(int $storageId, int $parentId): void {
		$this->ruleManager->getRulesForFilesByParent($this->user, $storageId, $parentId);
	}

	/**
	 * Filter a list to only the rules applicable to the current user
	 *
	 * @param list<Rule> $rules
	 * @return list<Rule>
	 */
	private function filterApplicableRulesToUser(array $rules): array {
		$userMappings = $this->userMappingManager->getMappingsForUser($this->user);
		return array_values(array_filter($rules, function (Rule $rule) use ($userMappings): bool {
			foreach ($userMappings as $userMapping) {
				if (
					$userMapping->getType() == $rule->getUserMapping()->getType()
					&& $userMapping->getId() == $rule->getUserMapping()->getId()
				) {
					return true;
				}
			}
			return false;
		}));
	}

	public function getBasePermission(int $folderId): int {
		// Can't use DI as it triggers an infinite loop
		$folderManager = Server::get(FolderManager::class);

		if ($folderManager->hasFolderACLDefaultNoPermission($folderId)) {
			$user = Server::get(IUserSession::class)->getUser();
			if ($user !== null && $folderManager->canManageACL($folderId, $user)) {
				// Give any ACL manager at least read permission, so they are able to navigate the folders and configure the ACLs.
				// Otherwise they are locked out completely. For default all permission we already prevent a self-lockout.
				return Constants::PERMISSION_READ;
			}

			return 0;
		}

		return Constants::PERMISSION_ALL;
	}
}
