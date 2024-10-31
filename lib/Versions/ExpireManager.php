<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Versions;

use OCA\Files_Versions\Expiration;
use OCA\Files_Versions\Versions\IMetadataVersion;
use OCA\Files_Versions\Versions\IVersion;

/**
 * TODO: move this to files_versions to be reused to apps in nc16
 */
class ExpireManager {
	public const MAX_VERSIONS_PER_INTERVAL = [
		//first 10sec, one version every 2sec
		1 => ['intervalEndsAfter' => 10, 'step' => 2],
		//next minute, one version every 10sec
		2 => ['intervalEndsAfter' => 60, 'step' => 10],
		//next hour, one version every minute
		3 => ['intervalEndsAfter' => 3600, 'step' => 60],
		//next 24h, one version every hour
		4 => ['intervalEndsAfter' => 86400, 'step' => 3600],
		//next 30days, one version per day
		5 => ['intervalEndsAfter' => 2592000, 'step' => 86400],
		//until the end one version per week
		6 => ['intervalEndsAfter' => -1, 'step' => 604800],
	];

	/** @var Expiration */
	private $expiration;

	public function __construct(Expiration $expiration) {
		$this->expiration = $expiration;
	}

	/**
	 * Get list of files we want to expire
	 *
	 * @param integer $time
	 * @param IVersion[] $versions
	 * @return IVersion[]
	 */
	protected function getAutoExpireList(int $time, array $versions): array {
		if (!$versions) {
			return [];
		}
		$toDelete = [];  // versions we want to delete

		// ensure the versions are sorted newest first
		usort($versions, function (IVersion $a, IVersion $b) {
			return $b->getTimestamp() <=> $a->getTimestamp();
		});

		$interval = 1;
		$step = self::MAX_VERSIONS_PER_INTERVAL[$interval]['step'];
		if (self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'] === -1) {
			$nextInterval = -1;
		} else {
			$nextInterval = $time - self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'];
		}

		/** @var IVersion $firstVersion */
		$firstVersion = array_shift($versions);
		$prevTimestamp = $firstVersion->getTimestamp();
		$nextVersion = $firstVersion->getTimestamp() - $step;

		foreach ($versions as $version) {
			$newInterval = true;
			while ($newInterval) {
				if ($nextInterval === -1 || $prevTimestamp > $nextInterval) {
					if ($version->getTimestamp() > $nextVersion) {
						// Do not expire versions with a label.
						if (!($version instanceof IMetadataVersion) || $version->getMetadataValue('label') === null || $version->getMetadataValue('label') === '') {
							//distance between two version too small, mark to delete
							$toDelete[] = $version;
						}
					} else {
						$nextVersion = $version->getTimestamp() - $step;
						$prevTimestamp = $version->getTimestamp();
					}
					$newInterval = false; // version checked so we can move to the next one
				} else { // time to move on to the next interval
					$interval++;
					/** @psalm-suppress InvalidArrayOffset We know that $interval is <= 6 thanks to the -1 intervalEndsAfter in the last step */
					$step = self::MAX_VERSIONS_PER_INTERVAL[$interval]['step'];
					$nextVersion = $prevTimestamp - $step;
					if (self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'] === -1) {
						$nextInterval = -1;
					} else {
						$nextInterval = $time - self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'];
					}
					$newInterval = true; // we changed the interval -> check same version with new interval
				}
			}
		}

		return $toDelete;
	}

	/**
	 * @param IVersion[] $versions
	 * @param int $time
	 * @param boolean $quotaExceeded
	 * @return IVersion[]
	 */
	public function getExpiredVersion(array $versions, int $time, bool $quotaExceeded): array {
		if ($this->expiration->shouldAutoExpire()) {
			$autoExpire = $this->getAutoExpireList($time, $versions);
		} else {
			$autoExpire = [];
		}

		$versionsLeft = array_udiff($versions, $autoExpire, function (IVersion $a, IVersion $b) {
			return ($a->getRevisionId() <=> $b->getRevisionId()) *
				($a->getSourceFile()->getId() <=> $b->getSourceFile()->getId());
		});

		$expired = array_filter($versionsLeft, function (IVersion $version) use ($quotaExceeded) {
			// Do not expire current version.
			if ($version->getTimestamp() === $version->getSourceFile()->getMtime()) {
				return false;
			}

			// Do not expire versions with a label.
			if ($version instanceof IMetadataVersion && $version->getMetadataValue('label') !== null && $version->getMetadataValue('label') !== '') {
				return false;
			}

			return $this->expiration->isExpired($version->getTimestamp(), $quotaExceeded);
		});

		return array_merge($autoExpire, $expired);
	}
}
