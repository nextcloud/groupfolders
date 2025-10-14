<?php

/**
 * SPDX-FileCopyrightText: 2018-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\Files_Trashbin;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;

class Expiration {

	// how long do we keep files in the trash bin if no other value is defined in the config file (unit: days)
	public const DEFAULT_RETENTION_OBLIGATION = 30;
	public const NO_OBLIGATION = -1;

	public function __construct(IConfig $config, private ITimeFactory $timeFactory)
 {
 }

	public function setRetentionObligation(string $obligation)
 {
 }

	/**
	 * Is trashbin expiration enabled
	 * @return bool
	 */
	public function isEnabled()
 {
 }

	/**
	 * Check if given timestamp in expiration range
	 * @param int $timestamp
	 * @param bool $quotaExceeded
	 * @return bool
	 */
	public function isExpired($timestamp, $quotaExceeded = false)
 {
 }

	/**
	 * Get minimal retention obligation as a timestamp
	 *
	 * @return int|false
	 */
	public function getMinAgeAsTimestamp()
 {
 }

	/**
	 * @return bool|int
	 */
	public function getMaxAgeAsTimestamp()
 {
 }
}
