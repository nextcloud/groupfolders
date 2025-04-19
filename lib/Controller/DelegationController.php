<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Controller;

use OCA\Circles\CirclesManager;
use OCA\GroupFolders\Attribute\RequireGroupFolderAdmin;
use OCA\GroupFolders\ResponseDefinitions;
use OCA\GroupFolders\Service\DelegationService;
use OCA\Settings\Service\AuthorizedGroupService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @psalm-import-type GroupFoldersDelegationGroup from ResponseDefinitions
 * @psalm-import-type GroupFoldersDelegationCircle from ResponseDefinitions
 */
class DelegationController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected IConfig $config,
		protected IGroupManager $groupManager,
		protected DelegationService $delegation,
		protected AuthorizedGroupService $authorizedGroupService,
		protected ContainerInterface $container,
		protected IAppManager $appManager,
		protected IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Returns the list of all groups
	 *
	 * @return DataResponse<Http::STATUS_OK, list<GroupFoldersDelegationGroup>, array{}>
	 *
	 * 200: All groups returned
	 */
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/delegation/groups')]
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
	 * @return DataResponse<Http::STATUS_OK, list<GroupFoldersDelegationCircle>, array{}>
	 *
	 * 200: All circles returned
	 */
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/delegation/circles')]
	public function getAllCircles(): DataResponse {
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if (!$circlesEnabled) {
			return new DataResponse([]);
		}

		try {
			$circlesManager = Server::get(CirclesManager::class);
		} catch (ContainerExceptionInterface) {
			return new DataResponse([]);
		}

		// As admin, get all circles,
		// As non-admin, only returns circles current user is members of.
		/** @psalm-suppress PossiblyNullReference current user cannot be null */
		if ($this->groupManager->isAdmin($this->userSession->getUser()->getUID())) {
			$circlesManager->startSuperSession();
		} else {
			$circlesManager->startSession();
		}
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
	 * @param string $classname If the classname is
	 *                          - OCA\GroupFolders\Settings\Admin : It's reference to fields in Admin Privileges.
	 *                          - OCA\GroupFolders\Controller\DelegationController : It's just to specific the subadmins.
	 *                          They can only manage groupfolders in which they are added in the Advanced Permissions (groups only)
	 *
	 * @return DataResponse<Http::STATUS_OK, list<GroupFoldersDelegationGroup>, array{}>
	 *
	 * 200: Authorized groups returned
	 */
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/delegation/authorized-groups')]
	public function getAuthorizedGroups(string $classname = ''): DataResponse {
		$data = [];
		$authorizedGroups = $this->authorizedGroupService->findExistingGroupsForClass($classname);

		foreach ($authorizedGroups as $authorizedGroup) {
			$group = $this->groupManager->get($authorizedGroup->getGroupId());
			if ($group !== null) {
				$data[] = [
					'gid' => $group->getGID(),
					'displayName' => $group->getDisplayName(),
				];
			}
		}

		return new DataResponse($data);
	}
}
