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
use OCP\IUser;

class ACLManager {
	private $ruleManager;
	private $ruleCache;
	private $user;
	private $rootStorageId;

	public function __construct(RuleManager $ruleManager, IUser $user, int $rootStorageId) {
		$this->ruleManager = $ruleManager;
		$this->ruleCache = new CappedMemoryCache();
		$this->user = $user;
		$this->rootStorageId = $rootStorageId;
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
	 * @return Rule[]
	 */
	private function getRules(array $paths): array {
		if ($this->pathsAreCached($paths)) {
			$rules = array_map(function (string $path) {
				return $this->ruleCache->get($path);
			}, $paths);
		} else {
			$rules = $this->ruleManager->getRulesForFilesByPath($this->user, $this->rootStorageId, $paths);
			foreach ($rules as $path => $rulesForPath) {
				$this->ruleCache->set($path, $rulesForPath);
			}
		}

		return array_reduce($rules, function (array $flatRules, array $rulesForPath) {
			return array_merge($flatRules, array_values($rulesForPath));
		}, []);
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
		$rules = $this->getRules($this->getParents($path));

		ksort($rules);

		return array_reduce($rules, function (int $permissions, Rule $rule) {
			return $rule->applyPermissions($permissions);
		}, Constants::PERMISSION_ALL);
	}
}
