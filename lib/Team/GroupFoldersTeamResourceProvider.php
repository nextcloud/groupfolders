<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Team;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamResource;

class GroupFoldersTeamResourceProvider implements ITeamResourceProvider {
	/** Provider icon: the Team folders app glyph (folder with members), no fill so it inherits currentColor. */
	private const PROVIDER_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 18H4V8h16m0-2h-8l-2-2H4c-1.11 0-2 .89-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8c0-1.11-.9-2-2-2Z"/><path d="M12 9.4a1.75 1.75 0 0 1 1.75 1.75A1.75 1.75 0 0 1 12 12.9a1.75 1.75 0 0 1-1.75-1.75A1.75 1.75 0 0 1 12 9.4m-3.5 1.25c.28 0 .54.075.765.21-.075.715.135 1.425.565 1.98-.25.48-.75.81-1.33.81a1.5 1.5 0 0 1-1.5-1.5 1.5 1.5 0 0 1 1.5-1.5m7 0a1.5 1.5 0 0 1 1.5 1.5 1.5 1.5 0 0 1-1.5 1.5c-.58 0-1.08-.33-1.33-.81.43-.555.64-1.265.565-1.98.225-.135.485-.21.765-.21m-6.75 5.125c0-1.035 1.455-1.875 3.25-1.875s3.25.84 3.25 1.875v.875h-6.5v-.875M6 16.65v-.75c0-.695.945-1.28 2.225-1.45-.295.34-.475.81-.475 1.325v.875H6m12 0h-1.75v-.875c0-.515-.18-.985-.475-1.325 1.28.17 2.225.755 2.225 1.45Z"/></svg>';

	/** Per-resource icon: a plain folder, matching the files team resource provider. */
	private const FOLDER_ICON_SVG = '<svg xmlns="http://www.w3.org/2000/svg" id="mdi-folder" viewBox="0 0 24 24"><path d="M10,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8C22,6.89 21.1,6 20,6H12L10,4Z" /></svg>';

	public function __construct(
		private readonly IL10N $l,
		private readonly IURLGenerator $url,
		private readonly FolderManager $folderManager,
	) {
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('Team folders');
	}

	public function getIconSvg(): string {
		return self::PROVIDER_ICON_SVG;
	}

	public function getSharedWith(string $teamId): array {
		// Membership is gated by TeamManager before this runs, so we return every
		// team folder mapped to this circle without re-checking the current user.
		if ($this->folderManager->getCirclesManager() === null) {
			return [];
		}

		$resources = [];
		foreach ($this->folderManager->getAllFolders() as $folder) {
			$applicable = $folder->groups[$teamId] ?? null;
			if ($applicable === null || $applicable['type'] !== 'circle') {
				continue;
			}

			$resources[] = new TeamResource(
				$this,
				(string)$folder->id,
				$folder->mountPoint,
				$this->url->linkToRouteAbsolute('files.view.index', ['dir' => '/' . $folder->mountPoint]),
				iconSvg: self::FOLDER_ICON_SVG,
			);
		}

		return $resources;
	}

	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		if ($this->folderManager->getCirclesManager() === null) {
			return false;
		}

		$folder = $this->folderManager->getAllFolders()[(int)$resourceId] ?? null;
		if ($folder === null) {
			return false;
		}

		$applicable = $folder->groups[$teamId] ?? null;
		return $applicable !== null && $applicable['type'] === 'circle';
	}

	public function getTeamsForResource(string $resourceId): array {
		if ($this->folderManager->getCirclesManager() === null) {
			return [];
		}

		$folder = $this->folderManager->getAllFolders()[(int)$resourceId] ?? null;
		if ($folder === null) {
			return [];
		}

		$teamIds = [];
		foreach ($folder->groups as $entityId => $applicable) {
			if ($applicable['type'] === 'circle') {
				$teamIds[] = (string)$entityId;
			}
		}

		return $teamIds;
	}
}
