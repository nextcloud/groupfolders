<?php

/**
 * @author Baptiste Fotia <baptiste.fotia@hotmail.com> for Arawa (https://arawa.fr)
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

use OCP\IUserSession;
use OCP\IGroupManager;

class FoldersFilter {

	private IUserSession $userSession;
	private IGroupManager $groupManager;
	
	public function __construct(IUserSession $userSession, IGroupManager $groupManager, IConfig $config) {
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	/**
	 * @param array $folders
	 * @return array $folders for subadmin only
	 */
	public function getForSubAdmin($folders): array {
		$user = $this->userSession->getUser();
		$folders = array_filter($folders, function ($folder) use ($user) {
			if (!empty($folder['manage'])) {
				foreach ($folder['manage'] as $manager) {
					if ($manager['type'] === 'group') {
						if ($this->groupManager->isInGroup($user->getUid(), $manager['id'])) {
							return $folder;
						}
					} elseif ($manager['id'] === $user->getUid()) {
						return $folder;
					}
				}
			}
		});

		return $folders;
	}
}
