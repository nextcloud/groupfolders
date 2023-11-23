<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\ACL;

use OC\Cache\CappedMemoryCache;
use OCA\GroupFolders\Trash\TrashManager;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IUser;

class ACLManager {
	private CappedMemoryCache $ruleCache;
	/** @var callable */
	private $rootFolderProvider;

	public function __construct(
		private RuleManager $ruleManager,
		private TrashManager $trashManager,
		private IUser $user,
		callable $rootFolderProvider,
		private ?int $rootStorageId = null,
	) {
		$this->ruleCache = new CappedMemoryCache();
		$this->rootFolderProvider = $rootFolderProvider;
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
		$rules = array_combine($paths, array_map(function (string $path): ?array {
			return $this->ruleCache->get($path);
		}, $paths));

		$nonCachedPaths = array_filter($paths, function (string $path) use ($rules): bool {
			return !isset($rules[$path]);
		});

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
	 * @param string $path
	 * @return string[]
	 */
	private function getRelevantPaths(string $path): array {
		$paths = [];
		$fromTrashbin = str_starts_with($path, '__groupfolders/trash/');
		if ($fromTrashbin) {
			$rootName = explode('/', $path, 5)[3];
			$rootName = substr($rootName, 0, strrpos($rootName, '.d'));
		}
		while ($path !== '') {
			$paths[] = $path;
			$path = dirname($path);
			if ($fromTrashbin && ($path === '__groupfolders/trash')) {
				$trashItemRow = $this->trashManager->getTrashItemByFileName($rootName);
				$path = dirname('__groupfolders/' . $trashItemRow['folder_id'] . '/' . $trashItemRow['original_location']);
				$fromTrashbin = false;
			} elseif ($path === '.' || $path === '/') {
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
	 * @param string $path
	 * @param array<string, Rule[]> $rules list of rules per path
	 * @return int
	 */
	public function getPermissionsForPathFromRules(string $path, array $rules): int {
		$path = ltrim($path, '/');
		$relevantPaths = $this->getRelevantPaths($path);
		$rules = array_intersect_key($rules, array_flip($relevantPaths));
		return $this->calculatePermissionsForPath($rules);
	}

	/**
	 * @param array<string, Rule[]> $rules list of rules per path, sorted parent first
	 * @return int
	 */
	private function calculatePermissionsForPath(array $rules): int {
		// first combine all rules with the same path, then apply them on top of the current permissions
		// since $rules is sorted parent first rules for subfolders overwrite the rules from the parent
		return array_reduce($rules, function (int $permissions, array $rules): int {
			$mergedRule = Rule::mergeRules($rules);
			return $mergedRule->applyPermissions($permissions);
		}, Constants::PERMISSION_ALL);
	}

	/**
	 * Get the combined "lowest" permissions for an entire directory tree
	 *
	 * @param string $path
	 * @return int
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
}
