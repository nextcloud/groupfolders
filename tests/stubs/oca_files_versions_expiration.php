<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\Files_Versions;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class Expiration {

	// how long do we keep files a version if no other value is defined in the config file (unit: days)
	public const NO_OBLIGATION = -1;

	public function __construct(IConfig $config, private ITimeFactory $timeFactory, private LoggerInterface $logger)
 {
 }

	/**
	 * Is versions expiration enabled
	 * @return bool
	 */
	public function isEnabled(): bool
 {
 }

	/**
	 * Is default expiration active
	 */
	public function shouldAutoExpire(): bool
 {
 }

	/**
	 * Check if given timestamp in expiration range
	 * @param int $timestamp
	 * @param bool $quotaExceeded
	 * @return bool
	 */
	public function isExpired(int $timestamp, bool $quotaExceeded = false): bool
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
	 * Get maximal retention obligation as a timestamp
	 *
	 * @return int|false
	 */
	public function getMaxAgeAsTimestamp()
 {
 }
}
