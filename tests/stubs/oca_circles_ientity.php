<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles;

use OCA\Circles\Exceptions\MembershipNotFoundException;
use OCA\Circles\Model\Membership;

/**
 * Interface IMemberships
 *
 * @package OCA\Circles
 */
interface IEntity {
	/**
	 * @return string
	 */
	public function getSingleId(): string
 {
 }

	/**
	 * @param Membership[] $memberships
	 *
	 * @return $this
	 */
	public function setMemberships(array $memberships): self
 {
 }

	/**
	 * @return Membership[]
	 */
	public function getMemberships(): array
 {
 }

	/**
	 * @param string $singleId
	 * @param bool $detailed
	 *
	 * @return Membership
	 * @throws MembershipNotFoundException
	 */
	public function getLink(string $singleId, bool $detailed = false): Membership
 {
 }
}
