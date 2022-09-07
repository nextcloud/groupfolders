<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\ACL;

use OCP\IUser;

class ACLManagerFactory {
	private $ruleManager;
	private $rootFolderProvider;

	public function __construct(RuleManager $ruleManager, callable $rootFolderProvider) {
		$this->ruleManager = $ruleManager;
		$this->rootFolderProvider = $rootFolderProvider;
	}

	public function getACLManager(IUser $user, ?int $rootStorageId = null): ACLManager {
		return new ACLManager($this->ruleManager, $user, $this->rootFolderProvider, $rootStorageId);
	}
}
