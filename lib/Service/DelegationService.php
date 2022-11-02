<?php

/**
 * @author Cyrille Bollu <cyr.debian@bollu.be> for Arawa (https://arawa.fr)
 *
 * GroupFolders
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
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

	private AuthorizedGroupMapper $groupAuthorizationMapper;
	private IGroupManager $groupManager;
	private IUserSession $userSession;

	public function __construct(
		AuthorizedGroupMapper $groupAuthorizationMapper,
		IGroupManager $groupManager,
		IUserSession $userSession
	) {
		$this->groupAuthorizationMapper = $groupAuthorizationMapper;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
	}

	/**
	 * @return bool true is admin of nextcloud otherwise false.
	 */
	public function isAdminNextcloud(): bool {
		return $this->groupManager->isAdmin($this->userSession->getUser()->getUID());
	}

	/**
	 * @return bool true if the user is a delegated admin
	 */
	public function isDelegatedAdmin(): bool {
		return $this->getAccessLevel([
			self::CLASS_NAME_ADMIN_DELEGATION,
		]);
	}

	/**
	 * @return bool true if the user has api access
	 */
	public function hasApiAccess(): bool {
		if ($this->isAdminNextcloud()) {
			return true;
		}
		return $this->getAccessLevel([
			self::CLASS_API_ACCESS,
			self::CLASS_NAME_ADMIN_DELEGATION,
		]);
	}

	/**
	 * @return bool true if the user has api access
	 */
	public function hasOnlyApiAccess(): bool {
		return $this->getAccessLevel([
			self::CLASS_API_ACCESS,
		]);
	}

	private function getAccessLevel(array $settingClasses): bool {
		$authorized = false;
		$authorizedClasses = $this->groupAuthorizationMapper->findAllClassesForUser($this->userSession->getUser());
		foreach ($settingClasses as $settingClass) {
			$authorized = in_array($settingClass, $authorizedClasses, true);
			if ($authorized) {
				break;
			}
		}
		return $authorized;
	}
}
