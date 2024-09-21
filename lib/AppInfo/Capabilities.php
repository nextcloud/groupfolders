<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders\AppInfo;

use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;
use OCP\DB\Exception;
use OCP\IUser;
use OCP\IUserSession;

class Capabilities implements ICapability {
	public function __construct(
		private IUserSession $userSession,
		private FolderManager $folderManager,
		private IAppManager $appManager,
	) {
	}

	/**
	 * @throws Exception
	 * @throws RequestBuilderException
	 */
	public function getCapabilities(): array {
		$user = $this->userSession->getUser();
		if (!$user) {
			return [];
		}

		return [
			Application::APP_ID => [
				'appVersion' => $this->appManager->getAppVersion(Application::APP_ID),
				'hasGroupFolders' => $this->hasFolders($user),
			],
		];
	}

	/**
	 * @throws RequestBuilderException
	 * @throws Exception
	 */
	private function hasFolders(IUser $user): bool {
		$folders = $this->folderManager->getFoldersForUser($user);
		return count($folders) > 0;
	}
}
