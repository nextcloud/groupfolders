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


use OCP\IUser;

class ACLManagerFactory {
	private $ruleManager;
	private $rootFolderProvider;
	private $ruleCache;

	public function __construct(RuleManager $ruleManager, ACLRuleCache $ruleCache, callable $rootFolderProvider) {
		$this->ruleManager = $ruleManager;
		$this->ruleCache = $ruleCache;
		$this->rootFolderProvider = $rootFolderProvider;
	}

	public function getACLManager(IUser $user): ACLManager {
		return new ACLManager($this->ruleManager, $this->ruleCache, $user, $this->rootFolderProvider);
	}
}
