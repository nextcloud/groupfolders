<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Service;

use OC\Settings\AuthorizedGroupMapper;
use OCA\GroupFolders\Controller\DelegationController;
use OCA\GroupFolders\Settings\Admin;
use OCP\IGroupManager;
use OCP\IUserSession;

class DelegationService {
	/**
	 * Has access to the entire groupfolders
	 */
	private const CLASS_NAME_ADMIN_DELEGATION = Admin::class;

	/**
	 * Has access only to the groupfolders in which the user has advanced
	 * permissions.
	 */
	private const CLASS_API_ACCESS = DelegationController::class;

	public function __construct(
		private AuthorizedGroupMapper $groupAuthorizationMapper,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
	) {
	}

	public function isAdminNextcloud(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		return $this->groupManager->isAdmin($user->getUID());
	}

	public function isDelegatedAdmin(): bool {
		return $this->getAccessLevel([
			self::CLASS_NAME_ADMIN_DELEGATION,
		]);
	}

	public function hasApiAccess(): bool {
		if ($this->isAdminNextcloud()) {
			return true;
		}

		return $this->getAccessLevel([
			self::CLASS_API_ACCESS,
			self::CLASS_NAME_ADMIN_DELEGATION,
		]);
	}

	public function hasOnlyApiAccess(): bool {
		return $this->getAccessLevel([
			self::CLASS_API_ACCESS,
		]);
	}

	private function getAccessLevel(array $settingClasses): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}

		$authorized = false;
		$authorizedClasses = $this->groupAuthorizationMapper->findAllClassesForUser($user);
		foreach ($settingClasses as $settingClass) {
			$authorized = in_array($settingClass, $authorizedClasses, true);
			if ($authorized) {
				break;
			}
		}

		return $authorized;
	}
}
