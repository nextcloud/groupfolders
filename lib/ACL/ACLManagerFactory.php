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
		callable $rootFolderProvider,
	) {
		$this->rootFolderProvider = $rootFolderProvider;
	}

	public function getACLManager(IUser $user, ?int $rootStorageId = null): ACLManager {
		return new ACLManager(
			$this->ruleManager,
			$this->trashManager,
			$this->logger,
			$user,
			$this->rootFolderProvider,
			$rootStorageId,
			$this->config->getAppValue('groupfolders', 'acl-inherit-per-user', 'false') === 'true',
		);
	}
}
