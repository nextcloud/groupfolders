<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Model\Probes;

use OCA\Circles\Model\Member;

/**
 * Class MemberProbe
 *
 * @package OCA\Circles\Model\Probes
 */
class MemberProbe extends BasicProbe {
	/**
	 * allow the initiator as a requesting member
	 *
	 * @param bool $can
	 *
	 * @return $this
	 */
	public function canBeRequestingMembership(bool $can = true): self
 {
 }

	/**
	 * @return bool
	 */
	public function isRequestingMembership(): bool
 {
 }


	/**
	 * @param bool $include
	 *
	 * @return $this
	 */
	public function initiatorAsDirectMember(bool $include = true): self
 {
 }

	/**
	 * @return bool
	 */
	public function directMemberInitiator(): bool
 {
 }


	/**
	 * force the generation an initiator if visitor
	 *
	 * @return $this
	 */
	public function emulateVisitor(): self
 {
 }

	public function isEmulatingVisitor(): bool
 {
 }


	/**
	 * @return int
	 */
	public function getMinimumLevel(): int
 {
 }

	/**
	 * @return $this
	 */
	public function mustBeMember(bool $must = true): self
 {
 }

	/**
	 * @return $this
	 */
	public function mustBeModerator(): self
 {
 }

	/**
	 * @return $this
	 */
	public function mustBeAdmin(): self
 {
 }

	/**
	 * @return $this
	 */
	public function mustBeOwner(): self
 {
 }


	/**
	 * @return array
	 */
	public function getAsOptions(): array
 {
 }


	/**
	 * @return array
	 */
	public function JsonSerialize(): array
 {
 }
}
