<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\TeamSpace;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCP\Teams\Team;
use OCP\Teams\TeamFolder;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates the lifecycle of team spaces: team folders that belong to a
 * team (circle).
 *
 * This is the single owner of the team-space lifecycle. The "folder belongs
 * to team" relationship is stored as the `team_circle_id` column on
 * `group_folders` (set via {@see FolderManager::setTeamCircleId()}). This app
 * is the only persistence owner of that relationship; team lifecycle policy is
 * provided by the caller through the public OCP Teams contract.
 *
 * On team destruction the folder is **unlinked, not deleted**: the
 * `team_circle_id` is cleared and the applicable-group entry removed, but the
 * folder and its contents are preserved so an admin can restore access if
 * needed.
 */
class TeamSpaceService {
	private const string APP_DIRECTORY_NAME = '.system';

	public function __construct(
		private readonly FolderManager $folderManager,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Create a team folder, configure it for the circle, and mark it as
	 * belonging to the team.
	 *
	 * @param string $circleId The circle single id to link the folder to.
	 * @param string $mountPoint The sanitized, unique mount point name.
	 * @param int $quota Quota in bytes (0 = unlimited).
	 * @return int The created folder id.
	 * @throws \Exception on failure.
	 */
	public function createTeamSpace(string $circleId, string $mountPoint, int $quota = 0): int {
		$folderId = $this->folderManager->createFolder($mountPoint);

		try {
			$this->createAppDirectory($folderId);

			if ($quota > 0) {
				$this->folderManager->setFolderQuota($folderId, $quota);
			}

			$this->folderManager->addApplicableGroup($folderId, $circleId);
			$this->folderManager->setManageACL($folderId, 'circle', $circleId, true);

			$this->folderManager->setTeamCircleId($folderId, $circleId);
		} catch (\Exception $e) {
			$existingFolderId = $this->folderManager->getFolderIdByTeamCircleId($circleId);
			$this->logger->error(
				'Failed to configure team space, rolling back creation',
				[
					'circleId' => $circleId,
					'folderId' => $folderId,
					'exception' => $e,
				],
			);
			try {
				// Clear the team link first so the isTeamSpace guard in
				// removeFolder() does not reject the rollback.
				$this->folderManager->clearTeamCircleId($folderId);
				$this->folderManager->removeFolder($folderId);
			} catch (\Exception $rollbackException) {
				$this->logger->error(
					'Could not roll back team space creation, manual cleanup required',
					[
						'circleId' => $circleId,
						'folderId' => $folderId,
						'exception' => $rollbackException,
					],
				);
			}

			if ($existingFolderId !== null) {
				$this->logger->info('Another request created the team space first; using the existing folder', [
					'circleId' => $circleId,
					'folderId' => $existingFolderId,
				]);
				return $existingFolderId;
			}
			throw $e;
		}

		return $folderId;
	}

	/**
	 * Create the folder reserved for app data in a team space.
	 *
	 * @throws \RuntimeException when the folder cannot be created.
	 */
	private function createAppDirectory(int $folderId): void {
		$teamSpace = $this->folderManager->getFolder($folderId);
		if ($teamSpace === null) {
			throw new \RuntimeException('Created team space could not be found');
		}

		$storage = $this->folderStorageManager->getBaseStorageForFolder(
			$folderId,
			$teamSpace->useSeparateStorage(),
			$teamSpace,
		);

		if ((bool)$storage->is_dir(self::APP_DIRECTORY_NAME)) {
			return;
		}

		if (!$storage->mkdir(self::APP_DIRECTORY_NAME)) {
			throw new \RuntimeException('Could not create ' . self::APP_DIRECTORY_NAME . ' folder for team space');
		}

		$storage->getScanner()->scan(self::APP_DIRECTORY_NAME);
	}

	/**
	 * Unlink the team space from a team without deleting the folder.
	 *
	 * Clears the `team_circle_id` column and removes the applicable-group entry
	 * so the team no longer has access. The folder and its contents are
	 * preserved and become a regular team folder that can be managed via the
	 * groupfolders admin UI.
	 *
	 * This is the path used when a team is destroyed: the folder is kept so an
	 * admin can restore access if needed. It is also the admin escape hatch
	 * exposed via the circles OCS endpoint.
	 *
	 * @param string $circleId The circle single id.
	 * @return int|null The unlinked folder id, or null if no folder was linked.
	 */
	public function unlinkTeamSpace(string $circleId): ?int {
		$folderId = $this->folderManager->getFolderIdByTeamCircleId($circleId);
		if ($folderId === null) {
			return null;
		}

		$this->folderManager->clearTeamCircleId($folderId);
		$this->folderManager->removeApplicableGroup($folderId, $circleId);
		$this->logger->info('Unlinked team space from circle', [
			'circleId' => $circleId,
			'folderId' => $folderId,
		]);

		return $folderId;
	}

	/**
	 * Remove the team space that belongs to a team, deleting its contents.
	 *
	 * Clears the `team_circle_id` column first so the `isTeamSpace` guard in
	 * {@see FolderManager::removeFolder()} does not reject the deletion, then
	 * deletes the folder.
	 *
	 * @param string $circleId The circle single id.
	 * @return bool Whether a folder was found and removed.
	 */
	public function removeTeamSpace(string $circleId): bool {
		$folderId = $this->folderManager->getFolderIdByTeamCircleId($circleId);
		if ($folderId === null) {
			return false;
		}

		$this->folderManager->clearTeamCircleId($folderId);
		$this->folderManager->removeApplicableGroup($folderId, $circleId);
		$this->folderManager->removeFolder($folderId);
		$this->logger->info('Removed team space for circle', [
			'circleId' => $circleId,
			'folderId' => $folderId,
		]);

		return true;
	}

	/**
	 * Create a team space for a team that predates the feature (or return the
	 * existing one if the team already has a folder).
	 *
	 * Idempotent: if the team already owns a folder, its id is returned without
	 * creating a new one.
	 *
	 * @param Team $team The team to upgrade.
	 * @param int $quota Quota in bytes (0 = unlimited).
	 * @return int The folder id (existing or newly created).
	 * @throws \Exception on failure.
	 */
	public function upgradeTeamSpace(Team $team, int $quota = 0): int {
		$circleId = $team->getId();

		$existing = $this->folderManager->getFolderIdByTeamCircleId($circleId);
		if ($existing !== null) {
			return $existing;
		}

		$mountPoint = $this->generateUniqueMountPoint($this->pickBaseName($team));

		return $this->createTeamSpace($circleId, $mountPoint, $quota);
	}

	/**
	 * Find the team space that belongs to the given team.
	 *
	 * @return TeamFolder|null
	 */
	public function getTeamSpaceForCircle(string $circleId): ?TeamFolder {
		$folderId = $this->folderManager->getFolderIdByTeamCircleId($circleId);
		if ($folderId === null) {
			return null;
		}

		$folder = $this->folderManager->getFolder($folderId);
		if ($folder === null) {
			return null;
		}
		return new TeamFolder($folder->id, $folder->mountPoint, $folder->quota);
	}

	/**
	 * Update the storage quota of the team space belonging to the given team.
	 *
	 * @param string $circleId The circle single id.
	 * @param int $quota Quota in bytes; zero means unlimited.
	 * @return TeamFolder The updated folder.
	 * @throws \RuntimeException if no team space is linked to the team.
	 */
	public function updateTeamSpaceQuota(string $circleId, int $quota): TeamFolder {
		$folderId = $this->folderManager->getFolderIdByTeamCircleId($circleId);
		if ($folderId === null) {
			throw new \RuntimeException('No team space linked to this team');
		}

		$this->folderManager->setFolderQuota($folderId, $quota);

		$folder = $this->folderManager->getFolder($folderId);
		if ($folder === null) {
			throw new \RuntimeException('Team space could not be found after updating quota');
		}

		$this->createAppDirectory($folderId);

		return new TeamFolder($folder->id, $folder->mountPoint, $folder->quota);
	}

	/**
	 * Return all group folders directly accessible to the given team.
	 *
	 * This includes the team's dedicated team space as well as regular group
	 * folders that have been assigned to the team's circle.
	 *
	 * @return list<TeamFolder>
	 */
	public function getGroupFoldersForCircle(string $circleId): array {
		return array_map(
			static fn (FolderDefinition $folder): TeamFolder => new TeamFolder($folder->id, $folder->mountPoint, $folder->quota),
			$this->folderManager->getFoldersForCircle($circleId),
		);
	}

	/**
	 * Return existing folders directly available to a team that have not been
	 * made an exclusive team folder for another team.
	 *
	 * @return list<TeamFolder>
	 */
	public function getLinkableGroupFoldersForCircle(string $circleId): array {
		return array_values(array_map(
			static fn (FolderDefinition $folder): TeamFolder => new TeamFolder($folder->id, $folder->mountPoint, $folder->quota),
			array_filter(
				$this->folderManager->getFoldersForCircle($circleId),
				fn (FolderDefinition $folder): bool => !$folder->isTeamSpace()
					&& $this->folderManager->isExclusivelyAssignedToCircle($folder->id, $circleId),
			),
		));
	}

	/**
	 * Mark an existing, directly available folder as the team's exclusive
	 * team folder. The ownership and eligibility checks are repeated at the
	 * mutation boundary to preserve the one-to-one relationship.
	 *
	 * @throws \InvalidArgumentException When the folder is not eligible.
	 */
	public function linkExistingTeamSpace(string $circleId, int $folderId): void {
		if ($this->folderManager->getFolderIdByTeamCircleId($circleId) !== null) {
			return;
		}

		foreach ($this->folderManager->getFoldersForCircle($circleId) as $folder) {
			if ($folder->id === $folderId
				&& !$folder->isTeamSpace()
				&& $this->folderManager->isExclusivelyAssignedToCircle($folderId, $circleId)) {
				$this->folderManager->setTeamCircleId($folderId, $circleId);
				return;
			}
		}

		throw new \InvalidArgumentException('The folder is not available for this team');
	}

	/**
	 * Whether the given circle (looked up by single id) owns a team space.
	 */
	public function hasTeamSpace(string $circleId): bool {
		return $this->folderManager->getFolderIdByTeamCircleId($circleId) !== null;
	}

	/**
	 * Pick a non-empty base name for the team space mount point.
	 *
	 * Tries the display name first, then the circle name, then the single id.
	 */
	public function pickBaseName(Team $team): string {
		$displayName = trim($team->getDisplayName());
		if ($displayName !== '') {
			return $displayName;
		}

		$singleId = trim($team->getId());
		if ($singleId !== '') {
			return $singleId;
		}

		$this->logger->warning('pickBaseName: falling back to generic team-space');
		return 'team-space';
	}

	/**
	 * Generate a unique mount point based on the given base name.
	 *
	 * Falls back to a generic name if the sanitized base name is empty.
	 */
	public function generateUniqueMountPoint(string $baseName): string {
		$mountPoint = $this->sanitizeMountPoint($baseName);

		if ($mountPoint === '') {
			$mountPoint = 'team-space';
		}

		if (!$this->folderManager->mountPointExists($mountPoint)) {
			return $mountPoint;
		}

		$counter = 1;
		do {
			$candidate = $mountPoint . ' (' . $counter . ')';
			$counter++;
		} while ($this->folderManager->mountPointExists($candidate));

		return $candidate;
	}

	/**
	 * Sanitize a string so it can be used as a team folder mount point.
	 *
	 * Strips control characters, path separators and backslashes, and limits
	 * the length to a safe value for the `mount_point` column.
	 */
	public function sanitizeMountPoint(string $name): string {
		$name = trim($name);
		$name = preg_replace('/[\x00-\x1f\/\\\\]+/', '', $name) ?? '';
		$name = trim($name);

		$max = 255;
		if (mb_strlen($name) > $max) {
			$name = mb_substr($name, 0, $max);
		}

		return $name;
	}
}
