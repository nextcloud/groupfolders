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
	public function getSharedWith(string $teamId): array {
		$folder = $this->getTeamFolder($teamId);
		if ($folder === null) {
			return [];
		}

		return [new TeamResource(
			$this,
			(string)$folder->getId(),
			$folder->getMountPoint(),
			$this->urlGenerator->getAbsoluteURL('/apps/files/?dir=/' . rawurlencode($folder->getMountPoint())),
			iconSvg: $this->getIconSvg(),
		)];
	}

	#[\Override]
	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		$folder = $this->getTeamFolder($teamId);
		return $folder !== null && (string)$folder->getId() === $resourceId;
	}

	#[\Override]
	public function getTeamsForResource(string $resourceId): array {
		return [];
	}
}
