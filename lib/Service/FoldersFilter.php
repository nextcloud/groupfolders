<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

class FoldersFilter {
	private IUserSession $userSession;
	private IGroupManager $groupManager;

	public function __construct(IUserSession $userSession, IGroupManager $groupManager) {
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	/**
	 * @param array $folders List of all folders
	 * @return array $folders List of folders that the api user can access
	 */
	public function getForApiUser(array $folders): array {
		$user = $this->userSession->getUser();
		$folders = array_filter($folders, function (array $folder) use ($user) {
			foreach ($folder['manage'] as $manager) {
				if ($manager['type'] === 'group') {
					if ($this->groupManager->isInGroup($user->getUid(), $manager['id'])) {
						return true;
					}
				} elseif ($manager['id'] === $user->getUid()) {
					return true;
				}
			}
			return false;
		});

		return $folders;
	}
}
