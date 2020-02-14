<?php declare(strict_types=1);
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
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserSession;
use OCA\GroupFolders\Folder\FolderManager;

class ACLManager {
	private $ruleManager;
	private $ruleCache;
	private $user;
	/** @var int|null */
	private $rootStorageId = null;
	private $rootFolderProvider;
	private $folderManager;

	public function __construct(RuleManager $ruleManager, IUser $user, callable $rootFolderProvider, FolderManager $folderManager) {
		$this->ruleManager = $ruleManager;
		$this->ruleCache = new CappedMemoryCache();
		$this->user = $user;
		$this->rootFolderProvider = $rootFolderProvider;
		$this->folderManager = $folderManager;
	}

	private function getRootStorageId() {
		if ($this->rootStorageId === null) {
			$provider = $this->rootFolderProvider;
			/** @var IRootFolder $rootFolder */
			$rootFolder = $provider();
			$this->rootStorageId = $rootFolder->getMountPoint()->getNumericStorageId();
		}

		return $this->rootStorageId;
	}

	private function pathsAreCached(array $paths): bool {
		foreach ($paths as $path) {
			if (!$this->ruleCache->hasKey($path)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param int $folderId
	 * @param array $paths
	 * @return (Rule[])[]
	 */
	private function getRules(array $paths): array {
		if ($this->pathsAreCached($paths)) {
			$rules = array_combine($paths, array_map(function (string $path) {
				return $this->ruleCache->get($path);
			}, $paths));
		} else {
			$rules = $this->ruleManager->getRulesForFilesByPath($this->user, $this->getRootStorageId(), $paths);
			foreach ($rules as $path => $rulesForPath) {
				$this->ruleCache->set($path, $rulesForPath);
			}

			if (count($paths) > 2) {
				// also cache the direct sibling since it's likely that we'll be needing those later
				$directParent = $paths[1];
				$siblingRules = $this->ruleManager->getRulesForFilesByParent($this->user, $this->getRootStorageId(), $directParent);
				foreach ($siblingRules as $path => $rulesForPath) {
					$this->ruleCache->set($path, $rulesForPath);
				}
			}
		}
		ksort($rules);

		return $rules;
	}

	/**
	 * @param string $path
	 * @return string[]
	 */
	private function getParents(string $path): array {
		$paths = [$path];
		while ($path !== '') {
			$path = dirname($path);
			if ($path === '.' || $path === '/') {
				$path = '';
			}
			$paths[] = $path;
		}

		return $paths;
	}

	private function pathToFolderId($path): ?int {
		$matches = null;
		if (preg_match('|__groupfolders/(\d+).*|', $path, $matches) === 0) {
			return null;
		}

		return (int)$matches[1];
	}

	private function isSuperAdmin($path): bool {
		$folderId = $this->pathToFolderId($path);
		if ($folderId === null) {
			return false;
		}

		return $this->folderManager->isSuperAdmin($folderId, $this->user->getUID());
	}

	private function isAccessAllowedByDefault($path): bool {
		$folderId = $this->pathToFolderId($path);
		if ($folderId === null) {
			return true;
		}

		return $this->folderManager->isAccessAllowedByDefault($folderId);
	}

	public function getACLPermissionsForPath(string $path): int {
		$path = ltrim($path, '/');
		$rulesByPath = $this->getRules($this->getParents($path));
		$rulesGroups = [];
		$pathsCnt = count($rulesByPath);
		$nthPath = 0;
		$superAdmin = $this->isSuperAdmin($path);
		$allowAccess = $this->isAccessAllowedByDefault($path);
		foreach ($rulesByPath as $rules) {
			$nthPath++;
			foreach ($rules as $rule) {
				if (!$rule->isInherit() && $nthPath !== $pathsCnt) {
					$rulesGroups = [];
					continue 2;
				}
			}

			if (count($rules) !== 0) {
				$rulesGroups[] = $rules;
			}
		}

		if (count($rulesGroups) === 0) {
			if ($allowAccess) {
				return Constants::PERMISSION_ALL;
			}

			return $superAdmin ? Constants::PERMISSION_READ : 0;
		}

		$permissions = array_reduce($rulesGroups, function (int $permissions, array $rules) {
			$mergedRule = Rule::mergeRules($rules);
			return $mergedRule->applyPermissions($permissions);
		}, Constants::PERMISSION_ALL);

		if ($superAdmin) {
			return $permissions | Constants::PERMISSION_READ;
		}

		return $permissions;
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

		return array_reduce($rules, function (int $permissions, array $rules) {
			$mergedRule = Rule::mergeRules($rules);

			$invertedMask = ~$mergedRule->getMask();
			// create a bitmask that has all inherit and allow bits set to 1 and all deny bits to 0
			$denyMask = $invertedMask | $mergedRule->getPermissions();

			// since we only care about the lower permissions, we ignore the allow values
			return $permissions & $denyMask;
		}, Constants::PERMISSION_ALL);
	}
}
