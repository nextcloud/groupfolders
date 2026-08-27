<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\TeamSpace;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Teams\ITeamFolderProvider;
use OCP\Teams\Team;
use OCP\Teams\TeamFolder;
use OCP\Teams\TeamResource;

class TeamSpaceProvider implements ITeamFolderProvider {
	public function __construct(
		private readonly TeamSpaceService $service,
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getId(): string {
		return 'groupfolders';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Team spaces');
	}

	#[\Override]
	public function getIconSvg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8z"/></svg>';
	}

	#[\Override]
	public function getTeamFolder(string $teamId): ?TeamFolder {
		return $this->service->getTeamSpaceForCircle($teamId);
	}

	#[\Override]
	public function createTeamFolder(Team $team, int $quota = 0): TeamFolder {
		$this->service->upgradeTeamSpace($team, $quota);

		$folder = $this->getTeamFolder($team->getId());
		if ($folder === null) {
			throw new \RuntimeException('Created team space could not be found');
		}

		return $folder;
	}

	#[\Override]
	public function unlinkTeamFolder(string $teamId): ?TeamFolder {
		$folder = $this->getTeamFolder($teamId);
		if ($folder === null) {
			return null;
		}

		$this->service->unlinkTeamSpace($teamId);
		return $folder;
	}

	#[\Override]
	public function removeTeamFolder(string $teamId): bool {
		return $this->service->removeTeamSpace($teamId);
	}

	#[\Override]
	/**
	 * @return list<TeamResource>
	 */
	public function getSharedWith(string $teamId): array {
		$teamSpaceFolder = $this->service->getTeamSpaceForCircle($teamId);
		$teamSpaceFolderId = $teamSpaceFolder !== null ? (string)$teamSpaceFolder->getId() : null;

		return array_map(function (TeamFolder $folder) use ($teamId, $teamSpaceFolderId): TeamResource {
			$url = $teamSpaceFolderId !== null && (string)$folder->getId() === $teamSpaceFolderId
				? $this->urlGenerator->linkToRouteAbsolute('circles.page.indexpath', ['path' => 'team/' . $teamId])
				: $this->urlGenerator->linkToRouteAbsolute('files.view.index', ['dir' => '/' . $folder->getMountPoint()]);

			return new TeamResource(
				$this,
				(string)$folder->getId(),
				$folder->getMountPoint(),
				$url,
				iconSvg: $this->getIconSvg(),
			);
		}, $this->service->getGroupFoldersForCircle($teamId));
	}

	#[\Override]
	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		foreach ($this->service->getGroupFoldersForCircle($teamId) as $folder) {
			if ((string)$folder->getId() === $resourceId) {
				return true;
			}
		}

		return false;
	}

	#[\Override]
	/**
	 * @return list<Team>
	 */
	public function getTeamsForResource(string $resourceId): array {
		return [];
	}
}
