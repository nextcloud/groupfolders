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

use OC\App\AppManager;
use OCA\GroupFolders\Service\DelegationService;
use OCA\Settings\Service\AuthorizedGroupService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class DelegationController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected IConfig $config,
		protected IGroupManager $groupManager,
		protected DelegationService $delegation,
		protected AuthorizedGroupService $authorizedGroupService,
		protected ContainerInterface $container,
		protected AppManager $appManager,
	) {
		parent::__construct($appName, $request);
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
	 * Returns the list of all visible circles
	 *
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function getAllCircles(): DataResponse {
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if (!$circlesEnabled) {
			return new DataResponse([]);
		}

		try {
			$circlesManager = Server::get(\OCA\Circles\CirclesManager::class);
		} catch (ContainerExceptionInterface $e) {
			return new DataResponse([]);
		}

		// Only get circles available to current user (as a normal non-admin user):
		// - publicly visible Circles,
		// - Circles the viewer is member of
		$circlesManager->startSession();
		$circles = $circlesManager->probeCircles();

		// transform in a format suitable for the app
		$data = [];
		foreach ($circles as $circle) {
			$data[] = [
				'singleId' => $circle->getSingleId(),
				'displayName' => $circle->getDisplayName(),
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
