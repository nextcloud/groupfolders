<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\Versions;

use OCA\Files_Versions\Expiration;
use OCA\Files_Versions\Versions\IVersion;

/**
 * TODO: move this to files_versions to be reused to apps in nc16
 */
class ExpireManager {
	const MAX_VERSIONS_PER_INTERVAL = [
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
	 * get list of files we want to expire
	 *
	 * @param integer $time
	 * @param IVersion[] $versions
	 * @return IVersion[]
	 */
	protected function getAutoExpireList(int $time, $versions) {
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
						//distance between two version too small, mark to delete
						$toDelete[] = $version;
					} else {
						$nextVersion = $version->getTimestamp() - $step;
						$prevTimestamp = $version->getTimestamp();
					}
					$newInterval = false; // version checked so we can move to the next one
				} else { // time to move on to the next interval
					$interval++;
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
	public function getExpiredVersion($versions, int $time, bool $quotaExceeded) {
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
			return $this->expiration->isExpired($version->getTimestamp(), $quotaExceeded);
		});

		return array_merge($autoExpire, $expired);
	}
}
