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

	public function __construct(
		string $AppName,
		IConfig $config,
		IGroupManager $groupManager,
		IRequest $request,
		DelegationService $delegation,
		AuthorizedGroupService $authorizedGroupService
	) {
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->groupManager = $groupManager;
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
				'gid' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}

		return new DataResponse($data);
	}

	/**
	 * Get the list Groups related to classname.
	 * If the classname is
	 * 	- OCA\GroupFolders\Settings\Admin : It's reference to fields in Admin Priveleges.
	 * 	- OCA\GroupFolders\Controller\DelegationController : It's just to specific the subadmins.
	 *	  They can only manage groupfolders in which they are added in the Advanced Permissions (groups only)
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function getAuthorizedGroups(string $classname = ""): DataResponse {
		$data = [];
		$authorizedGroups = $this->authorizedGroupService->findExistingGroupsForClass($classname);

		foreach ($authorizedGroups as $authorizedGroup) {
			$group = $this->groupManager->get($authorizedGroup->getGroupId());
			$data[] = [
				'gid' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}

		return new DataResponse($data);
	}
}
