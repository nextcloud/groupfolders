<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Model;

use JsonSerializable;
use OCA\Circles\Exceptions\FederatedUserNotFoundException;
use OCA\Circles\Exceptions\MembershipNotFoundException;
use OCA\Circles\Exceptions\OwnerNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\UnknownInterfaceException;
use OCA\Circles\IEntity;
use OCA\Circles\IFederatedUser;
use OCA\Circles\Tools\Db\IQueryRow;
use OCA\Circles\Tools\Exceptions\InvalidItemException;
use OCA\Circles\Tools\IDeserializable;
use OCA\Circles\Tools\Traits\TArrayTools;
use OCA\Circles\Tools\Traits\TDeserialize;

/**
 * Class FederatedUser
 *
 * @package OCA\Circles\Model
 */
class FederatedUser extends ManagedModel implements
	IFederatedUser,
	IEntity,
	IDeserializable,
	IQueryRow,
	JsonSerializable {
	use TArrayTools;
	use TDeserialize;


	/**
	 * FederatedUser constructor.
	 */
	public function __construct() {
	}


	/**
	 * @param string $userId
	 * @param string $instance
	 * @param int $type
	 * @param string $displayName
	 * @param Circle|null $basedOn
	 *
	 * @return $this
	 */
	public function set(string $userId, string $instance = '', int $type = Member::TYPE_USER, string $displayName = '', ?Circle $basedOn = null): self
 {
 }


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
	 * @return FederatedUser
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
	 * @return bool
	 */
	public function hasBasedOn(): bool
 {
 }

	/**
	 * @param Circle|null $basedOn
	 *
	 * @return $this
	 */
	public function setBasedOn(Circle $basedOn): self
 {
 }

	/**
	 * @return Circle
	 */
	public function getBasedOn(): Circle
 {
 }


	/**
	 * @param int $config
	 *
	 * @return self
	 */
	public function setConfig(int $config): self
 {
 }

	/**
	 * @return int
	 */
	public function getConfig(): int
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

	/**
	 * @return string
	 */
	public function getInstance(): string
 {
 }


	/**
	 * @return bool
	 */
	public function isLocal(): bool
 {
 }

	/**
	 * @return bool
	 */
	public function hasInheritance(): bool
 {
 }

	/**
	 * @param Membership $inheritance
	 *
	 * @return $this
	 */
	public function setInheritance(Membership $inheritance): self
 {
 }

	/**
	 * @return Membership
	 */
	public function getInheritance(): Membership
 {
 }


	/**
	 * @return bool
	 */
	public function hasMemberships(): bool
 {
 }

	/**
	 * @param array $memberships
	 *
	 * @return self
	 */
	public function setMemberships(array $memberships): IEntity
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
	 * @throws RequestBuilderException
	 */
	public function getLink(string $singleId, bool $detailed = false): Membership
 {
 }


	/**
	 * @param array $data
	 *
	 * @return $this
	 * @throws InvalidItemException
	 */
	public function import(array $data): IDeserializable
 {
 }


	/**
	 * @param Circle $circle
	 *
	 * @return FederatedUser
	 * @throws OwnerNotFoundException
	 */
	public function importFromCircle(Circle $circle): self
 {
 }


	/**
	 * @param array $data
	 * @param string $prefix
	 *
	 * @return IQueryRow
	 * @throws FederatedUserNotFoundException
	 */
	public function importFromDatabase(array $data, string $prefix = ''): IQueryRow
 {
 }


	/**
	 * @return string[]
	 * @throws UnknownInterfaceException
	 */
	public function jsonSerialize(): array
 {
 }


	/**
	 * @param IFederatedUser $member
	 *
	 * @return bool
	 */
	public function compareWith(IFederatedUser $member): bool
 {
 }
}
