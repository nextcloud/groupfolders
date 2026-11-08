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

/**
 * Translates between the folder mount point stored in the database (e.g. "Marketing")
 * and the path the folder is mounted at inside every user's home (e.g. "TeamFolders/Marketing").
 *
 * The parent folder is configured system wide via
 *   occ config:app:set groupfolders mount_prefix --value="TeamFolders"
 * By default no parent folder is used and folders are mounted in the user's root.
 *
 * By default the parent folder is only used for folders that belong to a team
 * (created or linked from the Teams app). Admin created folders keep their path unless
 *   occ config:app:set groupfolders mount_prefix_scope --value="all"
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
	 * Whether the parent folder is used for the given folder.
	 */
	public function appliesTo(FolderDefinition $folder): bool {
		// A folder mounted at "/" replaces the user's home folder and is never moved
		if ($this->getPrefix() === '' || $folder->mountPoint === self::ROOT_MOUNT_POINT) {
			return false;
		}

		return $this->getScope() === self::SCOPE_ALL || $folder->isTeamSpace();
	}

	/**
	 * Path relative to the user's "files" folder where the given folder is mounted.
	 */
	public function getUserMountPath(FolderDefinition $folder): string {
		if (!$this->appliesTo($folder)) {
			return $folder->mountPoint;
		}

		return $this->getPrefix() . '/' . $folder->mountPoint;
	}

	/**
	 * Mount points (as stored in the database) a folder mounted at the given user path can have.
	 *
	 * Callers have to check the folders they find with {@see getUserMountPath()}, since
	 * whether the parent folder is used depends on the folder itself.
	 *
	 * @return list<string>
	 */
	public function getMountPointCandidates(string $userPath): array {
		$userPath = trim($userPath, '/');

		// The root of the user's files can only be a folder mounted at "/"
		if ($userPath === '') {
			return [self::ROOT_MOUNT_POINT];
		}

		$candidates = [$userPath];

		$prefix = $this->getPrefix();
		if ($prefix !== '' && str_starts_with($userPath, $prefix . '/')) {
			$candidates[] = substr($userPath, strlen($prefix) + 1);
		}

		return array_values(array_unique($candidates));
	}
}
