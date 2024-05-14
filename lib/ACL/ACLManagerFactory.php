<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\Trash\TrashManager;
use OCP\IConfig;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class ACLManagerFactory {
	private $rootFolderProvider;

	public function __construct(
		private RuleManager $ruleManager,
		private TrashManager $trashManager,
		private IConfig $config,
		private LoggerInterface $logger,
		private IUserMappingManager $userMappingManager,
		callable $rootFolderProvider,
	) {
		$this->rootFolderProvider = $rootFolderProvider;
	}

	public function getACLManager(IUser $user, ?int $rootStorageId = null): ACLManager {
		return new ACLManager(
			$this->ruleManager,
			$this->trashManager,
			$this->userMappingManager,
			$this->logger,
			$user,
			$this->rootFolderProvider,
			$rootStorageId,
			$this->config->getAppValue('groupfolders', 'acl-inherit-per-user', 'false') === 'true',
		);
	}
}
