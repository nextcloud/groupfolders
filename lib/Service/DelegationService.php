<?php

/**
 * @author Cyrille Bollu <cyr.debian@bollu.be>
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

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

class DelegationService {

	/** @var IConfig */
	private $config;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserSession */
	private $userSession;

	public function __construct(IConfig $config,
			IGroupManager $groupManager,
			IUserSession $userSession) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
	}

	/**
	 * Return true if user is an admin, or a member of a group that
	 * has been granted admin rights on groupfolders
	 *
	 * @return bool
	 */
	public function isAdmin() {
		$userId = $this->userSession->getUser()->getUID();
		if ($this->groupManager->isAdmin($userId)) {
			return true;
		}
		$allowedGroups = explode('|', $this->config->getAppValue('groupfolders', 'delegated-admins', 'admin'));
		$userGroups = $this->groupManager->getUserGroups($this->userSession->getUser());
		foreach($userGroups as $userGroup) {
			if (in_array($userGroup->getGID(), $allowedGroups)) {
				return true;
			}
		}
		return false;

	}

}

