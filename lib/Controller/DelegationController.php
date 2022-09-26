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

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Service\DelegationService;
use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;
use OCA\Settings\Service\AuthorizedGroupService;

class DelegationController extends OCSController {

	private IGroupManager $groupManager;
	private IConfig $config;
	private DelegationService $delegation;
	private AuthorizedGroupService $authorizedGroupService;

	public function __construct(string $AppName,
		IConfig $config,
		IGroupManager $groupManager,
		IRequest $request,
		DelegationService $delegation,
		AuthorizedGroupService $authorizedGroupService) {
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->groupManager =  $groupManager;
		$this->delegation = $delegation;
		$this->authorizedGroupService = $authorizedGroupService;
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
			];
		}

		// return info
		return new DataResponse($data);
	}

	/**
	 * Get the list Groups from the Group folders fields in Admin Priveleges
	 * @NoAdminRequired
	 */
	public function getAuthorizedGroups(): DataResponse {

		$data = [];
		$authorizedGroups = $this->authorizedGroupService->findExistingGroupsForClass(Application::CLASS_NAME_ADMIN_DELEGATION);
	
		foreach ($authorizedGroups as $authorizedGroup) {
			$group = $this->groupManager->get($authorizedGroup->getGroupId());
			$data[] = [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
			];
		}

		return new DataResponse($data);
	}

	/**
	 * Get the list of groups allowed to use groupfolders with API/REST.
	 * SubAdmins can manage groupfolders with they are added in the Advanced Permissions (groups only)
	 *
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 *
	 */
	public function getAllowedSubAdminGroups(): DataResponse {
		$groups = json_decode($this->config->getAppValue('groupfolders', 'delegated-sub-admins', '[]'));

		// transform in a format suitable for the app
		$data = [];
		foreach ($groups as $gid) {
			$group = $this->groupManager->get($gid);
			$data[] = [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
			];
		}
		return new DataResponse($data);
	}


	/**
	 * @NoAdminRequired
	 * @param string $newGroups - It's the new list of gids
	 * Update the group list from Settings > Group folders.
	 * This update targets the same record as the Group folders fields in Admin Priveleges
	 */
	public function updateAuthorizedGroups(string $newGroups): DataResponse {
		$newGroups = json_decode($newGroups, true);
		$currentGroups = $this->authorizedGroupService->findExistingGroupsForClass(Application::CLASS_NAME_ADMIN_DELEGATION);

		foreach ($currentGroups as $group) {
			/** @var AuthorizedGroup $group */
			$removed = true;
			foreach ($newGroups as $gid) {
				if ($gid === $group->getGroupId()) {
					$removed = false;
					break;
				}
			}
			if ($removed) {
				$this->authorizedGroupService->delete($group->getId());
			}
		}

		foreach ($newGroups as $gid) {
			$added = true;
			foreach ($currentGroups as $group) {
				/** @var AuthorizedGroup $group */
				if ($gid === $group->getGroupId()) {
					$added = false;
					break;
				}
			}
			if ($added) {
				$this->authorizedGroupService->create($gid, Application::CLASS_NAME_ADMIN_DELEGATION);
			}
		}

		return new DataResponse(['valid' => true]);
	}

	/**
	 * Update the list of groups allowed to use groupfolders as subadmin
	 * SubAdmins can manage groupfolders with they are added in the Advanced Permissions (groups only)
	 * @param string $groups - it's a list of gids
	 */
	public function updateAllowedSubAdminGroups($groups): DataResponse {
		$this->config->setAppValue('groupfolders', 'delegated-sub-admins', $groups);
		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * Check if a user is admin or not.
	 */
	public function isAdminNextcloud(): DataResponse {
		return new DataResponse([
			'is_admin_nextcloud' => $this->delegation->isAdminNextcloud()
		], Http::STATUS_OK);
	}
}
