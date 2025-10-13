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
	 * @param string $singleId
	 *
	 * @return self
	 */
	public function setSingleId(string $singleId): self
 {
 }

	/**
	 * @return string
	 */
	public function getSingleId(): string
 {
 }

	/**
	 * @param string $userId
	 *
	 * @return self
	 */
	public function setUserId(string $userId): self
 {
 }

	/**
	 * @return string
	 */
	public function getUserId(): string
 {
 }

	/**
	 * @param int $userType
	 *
	 * @return self
	 */
	public function setUserType(int $userType): self
 {
 }

	/**
	 * @return int
	 */
	public function getUserType(): int
 {
 }

	/**
	 * @param string $displayName
	 *
	 * @return IFederatedUser
	 */
	public function setDisplayName(string $displayName): self
 {
 }

	/**
	 * @return string
	 */
	public function getDisplayName(): string
 {
 }

	/**
	 * @param ?Circle $basedOn
	 *
	 * @return $this
	 */
	public function setBasedOn(?Circle $basedOn): self
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

	/**
	 * @param string $instance
	 *
	 * @return self
	 */
	public function setInstance(string $instance): self
 {
 }
}
