<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Translates between the folder mount point stored in the database (e.g. "Marketing")
 * and the path the folder is mounted at inside a user's files (e.g. "TeamFolders/Marketing").
 *
 * The parent folder is configured system wide via
 *   occ config:app:set groupfolders mount_prefix --value="TeamFolders"
 * An empty value (the default) keeps the regular behaviour: folders are mounted at their mount point.
 *
 * By default the parent folder is only used for folders that belong to a team
 * (created or linked from the Teams app). Admin created folders keep their path unless
 *   occ config:app:set groupfolders mount_prefix_scope --value="all"
 *
 * Because the parent folder is added at mount time, two folders can end up at the same path
 * for a user, e.g. the team folder "Project" and an admin folder stored as "TeamFolders/Project".
 * {@see resolveMountPaths()} resolves such collisions deterministically:
 *  1. Folders that are not moved below the parent folder always keep their stored mount point,
 *     so existing paths never change because of the parent folder.
 *  2. A folder whose path below the parent folder is taken falls back to its stored mount point.
 *  3. A folder that still has no free path is not mounted (this only happens when the
 *     database contains the same mount point twice). Within a step, the lower folder id wins.
 * Nothing is overwritten silently: the fallback and skipped folders are logged.
 *
 * The result only depends on the given folders and only folders mounted for the same user
 * can collide, so the full filesystem setup (all folders of the user) and the partial setup
 * (only the folders found by {@see getMountPointCandidates()}) mount every folder at the same path.
 */
class MountPathResolver {
	public const CONFIG_KEY = 'mount_prefix';
	public const DEFAULT_PREFIX = '';

	public const SCOPE_CONFIG_KEY = 'mount_prefix_scope';
	public const SCOPE_TEAMS = 'teams';
	public const SCOPE_ALL = 'all';
	public const DEFAULT_SCOPE = self::SCOPE_TEAMS;

	/** Mount point of a folder that is mounted as the user's home folder, see {@see \OCA\GroupFolders\Folder\FolderManager::trimMountpoint()} */
	public const ROOT_MOUNT_POINT = '/';

	private ?string $prefix = null;
	private ?string $scope = null;

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * The sanitized parent folder (no leading/trailing slashes), or '' when disabled.
	 */
	public function getPrefix(): string {
		if ($this->prefix === null) {
			$this->prefix = self::sanitize(
				$this->appConfig->getValueString(Application::APP_ID, self::CONFIG_KEY, self::DEFAULT_PREFIX)
			);
		}

		return $this->prefix;
	}

	/**
	 * Which folders are mounted below the parent folder: {@see SCOPE_TEAMS} or {@see SCOPE_ALL}.
	 */
	public function getScope(): string {
		if ($this->scope === null) {
			$scope = $this->appConfig->getValueString(Application::APP_ID, self::SCOPE_CONFIG_KEY, self::DEFAULT_SCOPE);
			$this->scope = $scope === self::SCOPE_ALL ? self::SCOPE_ALL : self::SCOPE_TEAMS;
		}

		return $this->scope;
	}

	public static function sanitize(string $prefix): string {
		$segments = [];
		foreach (explode('/', str_replace('\\', '/', $prefix)) as $segment) {
			$segment = trim($segment);
			if ($segment === '' || $segment === '.' || $segment === '..') {
				continue;
			}

			$segments[] = $segment;
		}

		return implode('/', $segments);
	}

	/**
	 * Whether a folder with the given stored mount point should be mounted below the parent folder.
	 *
	 * @param bool $teamFolder Whether the folder belongs to a team
	 */
	public function appliesToMountPoint(string $mountPoint, bool $teamFolder): bool {
		// A folder mounted at "/" replaces the user's home folder and is never moved
		if ($this->getPrefix() === '' || $mountPoint === self::ROOT_MOUNT_POINT) {
			return false;
		}

		return $teamFolder || $this->getScope() === self::SCOPE_ALL;
	}

	/**
	 * Whether the parent folder should be used for the given folder.
	 */
	public function appliesTo(FolderDefinition $folder): bool {
		return $this->appliesToMountPoint($folder->mountPoint, $folder->isTeamSpace());
	}

	/**
	 * Path relative to the user's files where a folder with the given stored mount point
	 * should be mounted, without taking collisions with other folders into account.
	 */
	public function getPreferredMountPathForMountPoint(string $mountPoint, bool $teamFolder): string {
		if (!$this->appliesToMountPoint($mountPoint, $teamFolder)) {
			return $mountPoint;
		}

		return $this->getPrefix() . '/' . $mountPoint;
	}

	/**
	 * Path relative to the user's files where the folder should be mounted, without taking
	 * collisions with other folders into account. Use {@see resolveMountPaths()} to get the
	 * path a folder is actually mounted at for a user.
	 */
	public function getPreferredMountPath(FolderDefinition $folder): string {
		return $this->getPreferredMountPathForMountPoint($folder->mountPoint, $folder->isTeamSpace());
	}

	/**
	 * Resolve the paths (relative to the user's files) the given folders are mounted at.
	 *
	 * The folders must contain every folder of the user that could collide with the folders
	 * the caller is interested in: either all folders of the user, or all folders found by
	 * {@see getMountPointCandidates()} for the requested paths.
	 *
	 * @param iterable<FolderDefinition> $folders
	 * @return array<int, string> folder id => mount path; folders that cannot be mounted are omitted
	 */
	public function resolveMountPaths(iterable $folders): array {
		$byId = [];
		foreach ($folders as $folder) {
			$byId[$folder->id] = $folder;
		}

		ksort($byId);

		/** @var array<string, int> $taken mount path => folder id */
		$taken = [];
		/** @var array<int, string> $paths */
		$paths = [];

		// 1. folders that are not moved keep their stored mount point
		$moved = [];
		foreach ($byId as $id => $folder) {
			if ($this->appliesTo($folder)) {
				$moved[$id] = $folder;
				continue;
			}

			$this->claim($taken, $paths, $folder, $folder->mountPoint);
		}

		// 2. moved folders take their path below the parent folder if it is free
		$fallback = [];
		foreach ($moved as $id => $folder) {
			$path = $this->getPreferredMountPath($folder);
			if (isset($taken[$path])) {
				$fallback[$id] = $folder;
				continue;
			}

			$taken[$path] = $id;
			$paths[$id] = $path;
		}

		// 3. otherwise they fall back to their stored mount point
		foreach ($fallback as $folder) {
			$this->logger->info('Team folder "{mountPoint}" (id {folderId}) is mounted at "{mountPoint}" instead of "{path}", because another folder is mounted there', [
				'app' => Application::APP_ID,
				'mountPoint' => $folder->mountPoint,
				'folderId' => $folder->id,
				'path' => $this->getPreferredMountPath($folder),
			]);
			$this->claim($taken, $paths, $folder, $folder->mountPoint);
		}

		ksort($paths);

		return $paths;
	}

	/**
	 * @param array<string, int> $taken
	 * @param array<int, string> $paths
	 */
	private function claim(array &$taken, array &$paths, FolderDefinition $folder, string $path): void {
		if (isset($taken[$path])) {
			$this->logger->warning('Folder "{mountPoint}" (id {folderId}) is not mounted, because folder {otherId} is already mounted at "{path}"', [
				'app' => Application::APP_ID,
				'mountPoint' => $folder->mountPoint,
				'folderId' => $folder->id,
				'otherId' => $taken[$path],
				'path' => $path,
			]);

			return;
		}

		$taken[$path] = $folder->id;
		$paths[$folder->id] = $path;
	}

	/**
	 * Stored mount points of all folders that can be mounted at the given path (relative to
	 * the user's files), including the folders they can collide with there.
	 *
	 * Callers have to resolve the folders they find with {@see resolveMountPaths()}.
	 *
	 * @return list<string>
	 */
	public function getMountPointCandidates(string $userPath): array {
		$userPath = trim($userPath, '/');

		// The root of the user's files can only be a folder mounted at "/"
		if ($userPath === '') {
			return [self::ROOT_MOUNT_POINT];
		}

		// a folder stored at this path: mounted here, or (if moved) the fallback location
		$candidates = [$userPath];

		$prefix = $this->getPrefix();
		if ($prefix !== '') {
			// a moved folder that is mounted here
			if (str_starts_with($userPath, $prefix . '/')) {
				$candidates[] = substr($userPath, strlen($prefix) + 1);
			}

			// a folder stored at "<prefix>/<path>" that occupies the path below the parent folder
			// of a moved folder stored at this path, which then falls back to this path
			$candidates[] = $prefix . '/' . $userPath;
		}

		return array_values(array_unique($candidates));
	}

	/**
	 * Stored mount points of all folders that can collide with a new folder.
	 *
	 * @param bool $teamFolder Whether the new folder belongs to a team
	 * @return list<string>
	 */
	public function getCollisionCandidates(string $mountPoint, bool $teamFolder): array {
		$candidates = $this->getMountPointCandidates($this->getPreferredMountPathForMountPoint($mountPoint, $teamFolder));
		$candidates[] = $mountPoint;

		return array_values(array_unique($candidates));
	}
}
