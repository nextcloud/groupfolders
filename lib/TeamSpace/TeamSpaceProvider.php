<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\TeamSpace;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountPathResolver;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Teams\ITeamFolderProvider;
use OCP\Teams\Team;
use OCP\Teams\TeamFolder;
use OCP\Teams\TeamResource;

class TeamSpaceProvider implements ITeamFolderProvider {
	public function __construct(
		private readonly TeamSpaceService $service,
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly FolderManager $folderManager,
		private readonly MountPathResolver $mountPathResolver,
		private readonly IUserSession $userSession,
	) {
	}

	#[\Override]
	public function getId(): string {
		return 'groupfolders';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Team folders');
	}

	#[\Override]
	public function getIconSvg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8z"/></svg>';
	}

	#[\Override]
	public function getTeamFolder(string $teamId): ?TeamFolder {
		$folder = $this->service->getTeamSpaceForCircle($teamId);

		return $folder !== null ? $this->withUserMountPath($folder) : null;
	}

	#[\Override]
	public function createTeamFolder(Team $team, int $quota = 0): TeamFolder {
		$this->service->upgradeTeamSpace($team, $quota);

		$folder = $this->getTeamFolder($team->getId());
		if ($folder === null) {
			throw new \RuntimeException('Created team folder could not be found');
		}

		return $folder;
	}

	/**
	 * @return list<TeamFolder>
	 */
	#[\Override]
	public function getLinkableTeamFolders(string $teamId): array {
		return $this->withUserMountPaths($this->service->getLinkableGroupFoldersForCircle($teamId));
	}

	#[\Override]
	public function linkTeamFolder(string $teamId, int $folderId): TeamFolder {
		$this->service->linkExistingTeamSpace($teamId, $folderId);

		$folder = $this->getTeamFolder($teamId);
		if ($folder === null) {
			throw new \RuntimeException('Linked team space could not be found');
		}

		return $folder;
	}

	#[\Override]
	public function updateTeamFolderQuota(string $teamId, int $quota): TeamFolder {
		return $this->withUserMountPath($this->service->updateTeamSpaceQuota($teamId, $quota));
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

		$mountPaths = null;

		return array_map(function (TeamFolder $folder) use ($teamId, $teamSpaceFolderId, &$mountPaths): TeamResource {
			if ($teamSpaceFolderId !== null && (string)$folder->getId() === $teamSpaceFolderId) {
				$url = $this->urlGenerator->linkToRouteAbsolute('circles.page.indexpath', ['path' => 'team/' . $teamId]);
			} else {
				$mountPaths ??= $this->getMountPathsForCurrentUser();
				$url = $this->urlGenerator->linkToRouteAbsolute('files.view.index', ['dir' => '/' . ltrim($this->getMountPath($folder, $mountPaths), '/')]);
			}

			return new TeamResource(
				$this,
				(string)$folder->getId(),
				$folder->getMountPoint(),
				$url,
				iconSvg: $this->getIconSvg(),
			);
		}, $this->service->getGroupFoldersForCircle($teamId));
	}

	/**
	 * The Teams app uses the mount point of a team folder as the path inside the current
	 * user's files, which can include the parent folder, see {@see MountPathResolver}.
	 */
	private function withUserMountPath(TeamFolder $folder): TeamFolder {
		return $this->withUserMountPaths([$folder])[0];
	}

	/**
	 * @param list<TeamFolder> $folders
	 * @return list<TeamFolder>
	 */
	private function withUserMountPaths(array $folders): array {
		if ($folders === []) {
			return [];
		}

		$mountPaths = $this->getMountPathsForCurrentUser();

		return array_map(
			fn (TeamFolder $folder): TeamFolder => new TeamFolder($folder->getId(), $this->getMountPath($folder, $mountPaths), $folder->getQuota()),
			$folders,
		);
	}

	/**
	 * The paths the folders of the current user are mounted at, relative to the user's files.
	 *
	 * @return array<int, string>
	 */
	private function getMountPathsForCurrentUser(): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [];
		}

		return $this->mountPathResolver->resolveMountPaths($this->folderManager->getFoldersForUser($user));
	}

	/**
	 * The path the folder is mounted at inside the user's files, which can include the parent
	 * folder for team folders, see {@see MountPathResolver}.
	 *
	 * @param array<int, string> $mountPaths
	 */
	private function getMountPath(TeamFolder $folder, array $mountPaths): string {
		if (isset($mountPaths[$folder->getId()])) {
			return $mountPaths[$folder->getId()];
		}

		// the folder is not mounted for the current user, link to where it is mounted by default
		$definition = $this->folderManager->getFolder($folder->getId());

		return $definition !== null
			? $this->mountPathResolver->getPreferredMountPath($definition)
			: $folder->getMountPoint();
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
