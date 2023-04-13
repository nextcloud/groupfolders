<?php

declare(strict_types=1);

namespace OCA\GroupFolders\AppInfo;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\Capabilities\ICapability;
use OCP\IUser;
use OCP\IUserSession;

class Capabilities implements ICapability {
	private IUserSession $userSession;
	private FolderManager $folderManager;

	public function __construct(IUserSession $userSession, FolderManager $folderManager) {
		$this->userSession = $userSession;
		$this->folderManager = $folderManager;
	}

	public function getCapabilities(): array {
		$user = $this->userSession->getUser();
		if (!$user) {
			return [];
		}
		return [
			Application::APP_ID => [
				'hasGroupFolders' => $this->hasFolders($user),
			],
		];
	}

	private function hasFolders(IUser $user): bool {
		$folders = $this->folderManager->getFoldersForUser($user);
		return count($folders) > 0;
	}
}
