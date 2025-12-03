<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles;

use OCA\Circles\Model\Circle;

/**
 * Interface IFederatedUser
 *
 * @package OCA\Circles
 */
interface IFederatedUser extends IFederatedModel {
	/**
	 * @return string
	 */
	public function getSingleId(): string
 {
 }

	/**
	 * @return string
	 */
	public function getUserId(): string
 {
 }

	/**
	 * @return int
	 */
	public function getUserType(): int
 {
 }

	/**
	 * @return string
	 */
	public function getDisplayName(): string
 {
 }

	/**
	 * @return Circle
	 */
	public function getBasedOn(): Circle
 {
 }

	/**
	 * @return bool
	 */
	public function hasBasedOn(): bool
 {
 }
}
