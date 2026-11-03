<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OCA\GroupFolders\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Translates between the folder mount point stored in the database (e.g. "Marketing")
 * and the path the folder is mounted at inside every user's home (e.g. "TeamFolders/Marketing").
 *
 * The parent folder is configured system wide via
 *   occ config:app:set groupfolders mount_prefix --value="TeamFolders"
 * By default no parent folder is used and folders are mounted in the user's root.
 */
class MountPathResolver {
	public const CONFIG_KEY = 'mount_prefix';
	public const DEFAULT_PREFIX = '';

	private ?string $prefix = null;

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
	 * Path relative to the user's "files" folder where the given folder is mounted.
	 */
	public function getUserMountPath(string $mountPoint): string {
		$prefix = $this->getPrefix();
		if ($prefix === '') {
			return $mountPoint;
		}

		return $prefix . '/' . $mountPoint;
	}

	/**
	 * Reverse of {@see getUserMountPath()}. Returns null when the path is not below the prefix.
	 */
	public function getMountPointFromUserPath(string $userPath): ?string {
		$userPath = trim($userPath, '/');
		$prefix = $this->getPrefix();
		if ($prefix === '') {
			return $userPath;
		}

		if (!str_starts_with($userPath, $prefix . '/')) {
			return null;
		}

		return substr($userPath, strlen($prefix) + 1);
	}
}
