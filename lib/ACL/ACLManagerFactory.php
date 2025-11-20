<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\IAppConfig;
use OCP\IUser;

class ACLManagerFactory {
	public function __construct(
		private readonly RuleManager $ruleManager,
		private readonly IAppConfig $config,
		private readonly IUserMappingManager $userMappingManager,
	) {
	}

	public function getACLManager(IUser $user): ACLManager {
		return new ACLManager(
			$this->ruleManager,
			$this->userMappingManager,
			$user,
			$this->config->getValueString('groupfolders', 'acl-inherit-per-user', 'false') === 'true',
		);
	}
}
