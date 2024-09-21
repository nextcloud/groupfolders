<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\Trash\TrashManager;
use OCP\Cache\CappedMemoryCache;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\Files\IRootFolder;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ACLManager {
	private CappedMemoryCache $ruleCache;

	public function __construct(
		private RuleManager $ruleManager,
		private TrashManager $trashManager,
		private LoggerInterface $logger,
		private IUser $user,
		private \Closure $rootFolderProvider,
		private ?int $rootStorageId = null,
		private bool $inheritMergePerUser = false,
	) {
		$this->ruleCache = new CappedMemoryCache();
	}

	private function getRootStorageId(): int {
		if ($this->rootStorageId === null) {
			$provider = $this->rootFolderProvider;
			/** @var IRootFolder $rootFolder */
			$rootFolder = $provider();
			$this->rootStorageId = $rootFolder->getMountPoint()->getNumericStorageId() ?? -1;
		}

		return $this->rootStorageId;
	}

	/**
	 * Get the list of rules applicable for a set of paths
	 *
	 * @param string[] $paths
	 * @param bool $cache whether to cache the retrieved rules
	 * @return array<string, Rule[]> sorted parent first
	 */
	private function getRules(array $paths, bool $cache = true): array {
		// beware: adding new rules to the cache besides the cap
		// might discard former cached entries, so we can't assume they'll stay
		// cached, so we read everything out initially to be able to return it
		$rules = array_combine($paths, array_map(fn (string $path): ?array => $this->ruleCache->get($path), $paths));

		$nonCachedPaths = array_filter($paths, fn (string $path): bool => !isset($rules[$path]));

		if (!empty($nonCachedPaths)) {
			$newRules = $this->ruleManager->getRulesForFilesByPath($this->user, $this->getRootStorageId(), $nonCachedPaths);
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
	 * Get a list of all path that might contain relevant rules when calculating the permissions for a path
	 *
	 * This contains the $path itself and any parent folder
	 *
	 * @return string[]
	 */
	private function getRelevantPaths(string $path): array {
		$paths = [];
		$fromTrashbin = str_starts_with($path, '__groupfolders/trash/');
		if ($fromTrashbin) {
			/* Exploded path will look like ["__groupfolders", "trash", "1", "folderName.d2345678", "rest/of/the/path.txt"] */
			[,,$groupFolderId,$rootTrashedItemName] = explode('/', $path, 5);
			$groupFolderId = (int)$groupFolderId;
			/* Remove the date part */
			$separatorPos = strrpos($rootTrashedItemName, '.d');
			if ($separatorPos === false) {
				throw new RuntimeException('Invalid trash item name ' . $rootTrashedItemName);
			}
			$rootTrashedItemDate = (int)substr($rootTrashedItemName, $separatorPos + 2);
			$rootTrashedItemName = substr($rootTrashedItemName, 0, $separatorPos);
		}

		while ($path !== '') {
			$paths[] = $path;
			$path = dirname($path);
			if ($fromTrashbin && ($path === '__groupfolders/trash')) {
				/* We are in trash and hit the root folder, continue looking for ACLs on parent folders in original location */
				/** @psalm-suppress PossiblyUndefinedVariable Variables are defined above */
				$trashItemRow = $this->trashManager->getTrashItemByFileName($groupFolderId, $rootTrashedItemName, $rootTrashedItemDate);
				$fromTrashbin = false;
				if ($trashItemRow) {
					$path = dirname('__groupfolders/' . $groupFolderId . '/' . $trashItemRow['original_location']);
					continue;
				} else {
					$this->logger->warning("failed to find trash item for $rootTrashedItemName deleted at $rootTrashedItemDate in folder $groupFolderId", ['app' => 'groupfolders']);
				}
			}

			if ($path === '.' || $path === '/') {
				$path = '';
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
	public function getRelevantRulesForPath(array $paths, bool $cache = true): array {
		$allPaths = [];
		foreach ($paths as $path) {
			$allPaths = array_unique(array_merge($allPaths, $this->getRelevantPaths($path)));
		}

		return $this->getRules($allPaths, $cache);
	}

	public function getACLPermissionsForPath(string $path): int {
		$path = ltrim($path, '/');
		$rules = $this->getRules($this->getRelevantPaths($path));

		return $this->calculatePermissionsForPath($rules);
	}

	/**
	 * @param array<string, Rule[]> $rules list of rules per path
	 */
	public function getPermissionsForPathFromRules(string $path, array $rules): int {
		$path = ltrim($path, '/');
		$relevantPaths = $this->getRelevantPaths($path);
		$rules = array_intersect_key($rules, array_flip($relevantPaths));

		return $this->calculatePermissionsForPath($rules);
	}

	/**
	 * @param array<string, Rule[]> $rules list of rules per path, sorted parent first
	 */
	private function calculatePermissionsForPath(array $rules): int {
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

			return $mergedRule->applyPermissions(Constants::PERMISSION_ALL);
		} else {
			// first combine all rules with the same path, then apply them on top of the current permissions
			// since $rules is sorted parent first rules for subfolders overwrite the rules from the parent
			return array_reduce($rules, function (int $permissions, array $rules): int {
				$mergedRule = Rule::mergeRules($rules);
				return $mergedRule->applyPermissions($permissions);
			}, Constants::PERMISSION_ALL);
		}
	}

	/**
	 * Get the combined "lowest" permissions for an entire directory tree
	 * @throws Exception
	 */
	public function getPermissionsForTree(string $path): int {
		$path = ltrim($path, '/');
		$rules = $this->ruleManager->getRulesForPrefix($this->user, $this->getRootStorageId(), $path);

		return array_reduce($rules, function (int $permissions, array $rules): int {
			$mergedRule = Rule::mergeRules($rules);

			$invertedMask = ~$mergedRule->getMask();
			// create a bitmask that has all inherit and allow bits set to 1 and all deny bits to 0
			$denyMask = $invertedMask | $mergedRule->getPermissions();

			// since we only care about the lower permissions, we ignore the allow values
			return $permissions & $denyMask;
		}, Constants::PERMISSION_ALL);
	}

	/**
	 * @throws Exception
	 */
	public function preloadRulesForFolder(string $path): void {
		$this->ruleManager->getRulesForFilesByParent($this->user, $this->getRootStorageId(), $path);
	}
}
