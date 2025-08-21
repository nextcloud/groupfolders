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
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IUser;
use OCP\IUserSession;

class Capabilities implements ICapability {
	private const CACHE_NAMESPACE = 'groupfolders::capabilities';
	private const CACHE_TTL = 300;

	private readonly ICache $groupFoldersCache;

	public function __construct(
		private readonly IUserSession $userSession,
		private readonly FolderManager $folderManager,
		private readonly IAppManager $appManager,
		ICacheFactory $cacheFactory,
	) {
		$this->groupFoldersCache = $cacheFactory->createLocal(self::CACHE_NAMESPACE);
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
		if ($user === null) {
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
		$key = 'hasFolders:' . $user->getUID();

		$cached = $this->groupFoldersCache->get($key);
		if ($cached !== null) {
			return (bool)$cached;
		}

		$folders = $this->folderManager->getFoldersForUser($user);
		$value = !empty($folders);
		$this->groupFoldersCache->set($key, $value, self::CACHE_TTL);

		return $value;
	}
}
