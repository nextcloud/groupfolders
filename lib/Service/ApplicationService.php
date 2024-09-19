<?php

/**
 * SPDX-FileCopyrightText: 2022 Baptiste Fotia <baptiste.fotia@arawa.fr> for Arawa (https://arawa.fr)
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Service;

use OCA\GroupFolders\AppInfo\Application;
use OCP\App\IAppManager;

class ApplicationService {
	public function __construct(
		private IAppManager $appManager,
	) {
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
