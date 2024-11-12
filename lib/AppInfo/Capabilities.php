<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GroupFolders\AppInfo;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;
use OCP\IUser;
use OCP\IUserSession;

class Capabilities implements ICapability {
	private IUserSession $userSession;
	private FolderManager $folderManager;
	private IAppManager $appManager;

	public function __construct(IUserSession $userSession, FolderManager $folderManager, IAppManager $appManager) {
		$this->userSession = $userSession;
		$this->folderManager = $folderManager;
		$this->appManager = $appManager;
	}

	/**
	 * @return array{
	 *     groupfolders?: array{
	 *         appVersion: string,
	 *         hasGroupFolders: bool,
	 *     },
	 * }
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

	private function hasFolders(IUser $user): bool {
		$folders = $this->folderManager->getFoldersForUser($user);
		return count($folders) > 0;
	}
}
