<?php

/**
 * @author Baptiste Fotia <baptiste.fotia@arawa.fr> for Arawa (https://arawa.fr)
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

use OCA\GroupFolders\AppInfo\Application;
use OCP\App\IAppManager;

class ApplicationService {
	private IAppManager $appManager;

	public function __construct(IAppManager $appManager) {
		$this->appManager = $appManager;
	}

	/**
	 * Check that all apps that depend on Groupfolders are installed
	 * @return boolean true if all apps are installed, false otherwise.
	 */
	public function checkAppsInstalled(): bool {
		$diffApps = array_diff(Application::APPS_USE_GROUPFOLDERS, $this->appManager->getInstalledApps());

		return empty($diffApps);
	}
}
