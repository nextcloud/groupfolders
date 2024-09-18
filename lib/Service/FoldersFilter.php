<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

class FoldersFilter {
	public function __construct(
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
	}

	/**
	 * @param array $folders List of all folders
	 * @return array List of folders that the api user can access
	 */
	public function getForApiUser(array $folders): array {
		$user = $this->userSession->getUser();

		return array_filter($folders, function (array $folder) use ($user): bool {
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
	}
}
