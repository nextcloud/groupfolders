<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\Cache\CappedMemoryCache;
use OCP\IAppConfig;
use OCP\IUser;

class ACLManagerFactory {
	/**
	 * Memoize one ACLManager (and therefore one rule cache) per user for the
	 * lifetime of the request. The factory is a request-scoped service, so
	 * sharing the instance lets cache warming (e.g. preloadRulesForFolder) and
	 * the subsequent per-item permission checks hit the same cache instead of
	 * each call resolving the rules again. ACL and group changes take effect on
	 * the next request.
	 *
	 * Capped so a request resolving many users (e.g. an occ command or background
	 * job) cannot grow the map without bound.
	 *
	 * @var CappedMemoryCache<ACLManager>
	 */
	private readonly CappedMemoryCache $managers;

	public function __construct(
		private readonly RuleManager $ruleManager,
		private readonly IAppConfig $config,
		private readonly IUserMappingManager $userMappingManager,
	) {
		$this->managers = new CappedMemoryCache();
	}

	public function getACLManager(IUser $user): ACLManager {
		$uid = $user->getUID();

		$aclManager = $this->managers->get($uid);
		if ($aclManager === null) {
			$aclManager = new ACLManager(
				$this->ruleManager,
				$this->userMappingManager,
				$user,
				$this->config->getValueString('groupfolders', 'acl-inherit-per-user', 'false') === 'true',
			);
			$this->managers->set($uid, $aclManager);
		}

		return $aclManager;
	}
}
