<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\TeamSpace;

use OCA\GroupFolders\Folder\FolderManager;
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
	public function __construct(
		private readonly FolderManager $folderManager,
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

		return new TeamFolder($folder->id, $folder->mountPoint);
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
