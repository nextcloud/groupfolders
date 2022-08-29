<?php

/**
 * @author Cyrille Bollu <cyr.debian@bollu.be> for Arawa (https://www.arawa.fr/)
 * @license GNU AGPL version 3
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

namespace OCA\GroupFolders\Controller;

use OCA\GroupFolders\Service\DelegationService;
use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;

class DelegationController extends OCSController {
	/** @var IGroupManager */
	private $groupManager;

	/** @var IConfig */
	private $config;

	/** @var DelegationService */
	private $delegation;

	public function __construct($AppName,
		IConfig $config,
		IGroupManager $groupManager,
		IRequest $request,
		DelegationService $delegation) {
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->delegation = $delegation;
	}

	/**
	 * Returns the list of all groups
	 *
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function getAllGroups(): DataResponse {
		// Get all groups
		$groups = $this->groupManager->search('');

		// transform in a format suitable for the app
		$data = [];
		foreach ($groups as $group) {
			$data[] = [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
				'usercount' => $group->count(),
				'disabled' => $group->countDisabled(),
				'canAdd' => $group->canAddUser(),
				'canRemove' => $group->canRemoveUser(),
			];
		}

		// return info
		return new DataResponse($data);
	}

	/**
	 * Get the list of groups allowed to use groupfolders
	 *
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 *
	 * @return DataResponse
	 */
	public function getAllowedGroups() {
		$groups = json_decode($this->config->getAppValue('groupfolders', 'delegated-admins', '[]'));

		// transform in a format suitable for the app
		$data = [];
		foreach ($groups as $gid) {
			$group = $this->groupManager->get($gid);
			$data[] = [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
				'usercount' => $group->count(),
				'disabled' => $group->countDisabled(),
				'canAdd' => $group->canAddUser(),
				'canRemove' => $group->canRemoveUser(),
			];
		}
		return new DataResponse($data);
	}

	/**
	 * Get the list of groups allowed to use groupfolders for subadmingroup
	 *
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 *
	 * @return DataResponse
	 */
	public function getAllowedSubAdminGroups() {
		$groups = json_decode($this->config->getAppValue('groupfolders', 'delegated-sub-admins', '[]'));

		// transform in a format suitable for the app
		$data = [];
		foreach ($groups as $gid) {
			$group = $this->groupManager->get($gid);
			$data[] = [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
				'usercount' => $group->count(),
				'disabled' => $group->countDisabled(),
				'canAdd' => $group->canAddUser(),
				'canRemove' => $group->canRemoveUser(),
			];
		}
		return new DataResponse($data);
	}

	/**
	 * Update the list of groups allowed to use groupfolders as admin
	 *
	 * @return DataResponse
	 */
	public function updateAllowedGroups($groups) {
		$this->config->setAppValue('groupfolders', 'delegated-admins', $groups);
		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Update the list of groups allowed to use groupfolders as subadmin
	 * @return DataResponse
	 */
	public function updateAllowedSubAdminGroups($groups) {
		$this->config->setAppValue('groupfolders', 'delegated-sub-admins', $groups);
		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @return boolean - return true if is admin.
	 */
	public function isAdmin() {
		return new DataResponse([ 'is_admin' => $this->delegation->isAdmin() ], Http::STATUS_OK);
	}
}
