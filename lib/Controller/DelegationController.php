<?php

/**
 * SPDX-FileCopyrightText: Cyrille Bollu <cyr.debian@bollu.be> for Arawa (https://www.arawa.fr/)
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\GroupFolders\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;

class DelegationController extends OCSController {
	private IGroupManager $groupManager;

	public function __construct(
		string $AppName,
		IGroupManager $groupManager,
		IRequest $request
	) {
		parent::__construct($AppName, $request);
		$this->groupManager = $groupManager;
	}

	/**
	 * Returns the list of all groups
	 *
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
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
}
