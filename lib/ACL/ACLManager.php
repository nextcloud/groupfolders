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

class ACLManager {
	private $ruleManager;
	private $ruleCache;
	private $user;
	/** @var int|null */
	private $rootStorageId = null;
	private $rootFolderProvider;

	public function __construct(RuleManager $ruleManager, ACLRuleCache $ruleCache, IUser $user, callable $rootFolderProvider) {
		$this->ruleManager = $ruleManager;
		$this->ruleCache = $ruleCache;
		$this->user = $user;
		$this->rootFolderProvider = $rootFolderProvider;
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

	/**
	 * @param int $folderId
	 * @param array $paths
	 * @return (Rule[])[]
	 */
	private function getRules(array $paths): array {
		$rules = $this->ruleCache->getByPaths($this->user, $this->getRootStorageId(), $paths);

		$uncachedPaths = array_values(array_diff($paths, array_keys($rules)));

		if (count($uncachedPaths) > 0) {
			$this->ruleCache->cachePath(
				$this->getRootStorageId(),
				$this->ruleManager->getAllRulesForFilesByPath($this->getRootStorageId(), $uncachedPaths)
			);
			$rules = $this->ruleCache->getByPaths($this->user, $this->getRootStorageId(), $paths);
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

	public function getACLPermissionsForPath(string $path): int {
		$path = ltrim($path, '/');
		$rules = $this->getRules($this->getParents($path));

		return array_reduce($rules, function (int $permissions, array $rules) {
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
