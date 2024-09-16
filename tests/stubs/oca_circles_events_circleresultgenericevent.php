<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Events;

use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\FederatedEvent;
use OCA\Circles\Model\Member;
use OCA\Circles\Tools\Model\SimpleDataStore;
use OCP\EventDispatcher\Event;

/**
 * Class CircleResultGenericEvent
 *
 * @package OCA\Circles\Events
 */
class CircleResultGenericEvent extends Event {
	/**
	 * CircleResultGenericEvent constructor.
	 *
	 * @param FederatedEvent $federatedEvent
	 * @param SimpleDataStore[] $results
	 */
	public function __construct(FederatedEvent $federatedEvent, array $results)
 {
 }


	/**
	 * @return FederatedEvent
	 */
	public function getFederatedEvent(): FederatedEvent
 {
 }


	/**
	 * @return SimpleDataStore[]
	 */
	public function getResults(): array
 {
 }


	/**
	 * @return Circle
	 */
	public function getCircle(): Circle
 {
 }


	/**
	 * @return bool
	 */
	public function hasMember(): bool
 {
 }

	/**
	 * @return Member|null
	 */
	public function getMember(): ?Member
 {
 }
}
