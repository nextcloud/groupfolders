<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Model;

use DateTime;
use JsonSerializable;
use OCA\Circles\AppInfo\Capabilities;
use OCA\Circles\Exceptions\MemberNotFoundException;
use OCA\Circles\Exceptions\MembershipNotFoundException;
use OCA\Circles\Exceptions\ParseMemberLevelException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\UnknownInterfaceException;
use OCA\Circles\Exceptions\UserTypeNotFoundException;
use OCA\Circles\IEntity;
use OCA\Circles\IFederatedUser;
use OCA\Circles\Model\Federated\RemoteInstance;
use OCA\Circles\Tools\Db\IQueryRow;
use OCA\Circles\Tools\Exceptions\InvalidItemException;
use OCA\Circles\Tools\IDeserializable;
use OCA\Circles\Tools\Traits\TArrayTools;
use OCA\Circles\Tools\Traits\TDeserialize;

/**
 * Class Member
 *
 * @package OCA\Circles\Model
 */
class Member extends ManagedModel implements
	IEntity,
	IFederatedUser,
	IDeserializable,
	IQueryRow,
	JsonSerializable {
	use TArrayTools;
	use TDeserialize;


	public const LEVEL_NONE = 0;
	public const LEVEL_MEMBER = 1;
	public const LEVEL_MODERATOR = 4;
	public const LEVEL_ADMIN = 8;
	public const LEVEL_OWNER = 9;

	public const TYPE_SINGLE = 0;
	public const TYPE_USER = 1;
	public const TYPE_GROUP = 2;
	public const TYPE_MAIL = 4;
	public const TYPE_CONTACT = 8;
	public const TYPE_CIRCLE = 16;
	public const TYPE_APP = 10000;

	public const ALLOWING_ALL_TYPES = 31;

	public const APP_CIRCLES = 10001;
	public const APP_OCC = 10002;
	public const APP_DEFAULT = 11000;


	public static $TYPE = [
		0 => 'single',
		1 => 'user',
		2 => 'group',
		4 => 'mail',
		8 => 'contact',
		16 => 'circle',
		10000 => 'app'
	];

	/**
	 * Note: When editing those values, update lib/Application/Capabilities.php
	 *
	 * @see Capabilities::generateConstantsMember()
	 */
	public const STATUS_INVITED = 'Invited';
	public const STATUS_REQUEST = 'Requesting';
	public const STATUS_MEMBER = 'Member';
	public const STATUS_BLOCKED = 'Blocked';


	/**
	 * Note: When editing those values, update lib/Application/Capabilities.php
	 *
	 * @see Capabilities::generateConstantsMember()
	 * @var array
	 */
	public static $DEF_LEVEL = [
		1 => 'Member',
		4 => 'Moderator',
		8 => 'Admin',
		9 => 'Owner'
	];


	public static $DEF_TYPE_MAX = 31;

	/**
	 * Member constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param string $id
	 *
	 * @return $this
	 */
	public function setId(string $id): self {
	}

	/**
	 * @return string
	 */
	public function getId(): string {
	}


	/**
	 * @param string $circleId
	 *
	 * @return Member
	 */
	public function setCircleId(string $circleId): self {
	}

	/**
	 * @return string
	 */
	public function getCircleId(): string {
	}


	/**
	 * This should replace user_id, user_type and instance; and will use the data from Circle with
	 * Config=CFG_SINGLE
	 *
	 * @param string $singleId
	 *
	 * @return $this
	 */
	public function setSingleId(string $singleId): self {
	}

	/**
	 * @return string
	 */
	public function getSingleId(): string {
	}


	/**
	 * @param string $userId
	 *
	 * @return Member
	 */
	public function setUserId(string $userId): self {
	}

	/**
	 * @return string
	 */
	public function getUserId(): string {
	}


	/**
	 * @param int $userType
	 *
	 * @return Member
	 */
	public function setUserType(int $userType): self {
	}

	/**
	 * @return int
	 */
	public function getUserType(): int {
	}

	/**
	 * @return int
	 * @deprecated 22.0.0 Use `getUserType()` instead
	 */
	public function getType(): int {
	}


	/**
	 * @param string $instance
	 *
	 * @return Member
	 */
	public function setInstance(string $instance): self {
	}

	/**
	 * @return string
	 */
	public function getInstance(): string {
	}


	/**
	 * @return bool
	 */
	public function isLocal(): bool {
	}


	/**
	 * @param FederatedUser $invitedBy
	 *
	 * @return Member
	 */
	public function setInvitedBy(FederatedUser $invitedBy): Member {
	}

	/**
	 * @return FederatedUser
	 */
	public function getInvitedBy(): FederatedUser {
	}

	/**
	 * @return bool
	 */
	public function hasInvitedBy(): bool {
	}


	/**
	 * @return bool
	 */
	public function hasRemoteInstance(): bool {
	}

	/**
	 * @param RemoteInstance $remoteInstance
	 *
	 * @return Member
	 */
	public function setRemoteInstance(RemoteInstance $remoteInstance): self {
	}

	/**
	 * @return RemoteInstance
	 */
	public function getRemoteInstance(): RemoteInstance {
	}


	/**
	 * @return bool
	 */
	public function hasBasedOn(): bool {
	}

	/**
	 * @param Circle $basedOn
	 *
	 * @return $this
	 */
	public function setBasedOn(Circle $basedOn): self {
	}

	/**
	 * @return Circle
	 */
	public function getBasedOn(): Circle {
	}


	/**
	 * @return bool
	 */
	public function hasInheritedBy(): bool {
	}

	/**
	 * @param FederatedUser $inheritedBy
	 *
	 * @return $this
	 */
	public function setInheritedBy(FederatedUser $inheritedBy): self {
	}

	/**
	 * @return FederatedUser
	 */
	public function getInheritedBy(): FederatedUser {
	}


	/**
	 * @return bool
	 */
	public function hasInheritanceFrom(): bool {
	}

	/**
	 * @param Member $inheritanceFrom
	 *
	 * @return $this
	 */
	public function setInheritanceFrom(Member $inheritanceFrom): self {
	}

	/**
	 * @return Member|null
	 */
	public function getInheritanceFrom(): ?Member {
	}


	/**
	 * @param int $level
	 *
	 * @return Member
	 */
	public function setLevel(int $level): self {
	}

	/**
	 * @return int
	 */
	public function getLevel(): int {
	}


	/**
	 * @param string $status
	 *
	 * @return Member
	 */
	public function setStatus(string $status): self {
	}

	/**
	 * @return string
	 */
	public function getStatus(): string {
	}


	/**
	 * @param array $notes
	 *
	 * @return Member
	 */
	public function setNotes(array $notes): self {
	}

	/**
	 * @return array
	 */
	public function getNotes(): array {
	}


	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function getNote(string $key): string {
	}

	/**
	 * @param string $key
	 *
	 * @return array
	 */
	public function getNoteArray(string $key): array {
	}

	/**
	 * @param string $key
	 * @param string $note
	 *
	 * @return $this
	 */
	public function setNote(string $key, string $note): self {
	}

	/**
	 * @param string $key
	 * @param array $note
	 *
	 * @return $this
	 */
	public function setNoteArray(string $key, array $note): self {
	}

	/**
	 * @param string $key
	 * @param JsonSerializable $obj
	 *
	 * @return $this
	 */
	public function setNoteObj(string $key, JsonSerializable $obj): self {
	}


	/**
	 * @param string $displayName
	 *
	 * @return Member
	 */
	public function setDisplayName(string $displayName): self {
	}


	/**
	 * @param int $displayUpdate
	 *
	 * @return Member
	 */
	public function setDisplayUpdate(int $displayUpdate): self {
	}

	/**
	 * @return int
	 */
	public function getDisplayUpdate(): int {
	}


	/**
	 * @return string
	 */
	public function getDisplayName(): string {
	}


	/**
	 * @param string $contactId
	 *
	 * @return Member
	 */
	public function setContactId(string $contactId): self {
	}

	/**
	 * @return string
	 */
	public function getContactId(): string {
	}


	/**
	 * @param string $contactMeta
	 *
	 * @return Member
	 */
	public function setContactMeta(string $contactMeta): self {
	}

	/**
	 * @return string
	 */
	public function getContactMeta(): string {
	}


	/**
	 * @param Circle $circle
	 *
	 * @return self
	 */
	public function setCircle(Circle $circle): self {
	}

	/**
	 * @return Circle
	 */
	public function getCircle(): Circle {
	}

	/**
	 * @return bool
	 */
	public function hasCircle(): bool {
	}


	/**
	 * @param int $joined
	 *
	 * @return Member
	 */
	public function setJoined(int $joined): self {
	}

	/**
	 * @return int
	 */
	public function getJoined(): int {
	}


	/**
	 * @return bool
	 */
	public function hasMemberships(): bool {
	}

	/**
	 * @param array $memberships
	 *
	 * @return self
	 */
	public function setMemberships(array $memberships): IEntity {
	}

	/**
	 * @return Membership[]
	 */
	public function getMemberships(): array {
	}


	/**
	 * @param string $singleId
	 * @param bool $detailed
	 *
	 * @return Membership
	 * @throws MembershipNotFoundException
	 * @throws RequestBuilderException
	 */
	public function getLink(string $singleId, bool $detailed = false): Membership {
	}

	/**
	 * @param string $circleId
	 * @param bool $detailed
	 *
	 * @return Membership
	 * @throws MembershipNotFoundException
	 * @throws RequestBuilderException
	 * @deprecated - use getLink();
	 */
	public function getMembership(string $circleId, bool $detailed = false): Membership {
	}


	/**
	 * @param Member $member
	 * @param bool $full
	 *
	 * @return bool
	 */
	public function compareWith(Member $member, bool $full = true): bool {
	}


	/**
	 * @param array $data
	 *
	 * @return $this
	 * @throws InvalidItemException
	 */
	public function import(array $data): IDeserializable {
	}


	/**
	 * @param array $data
	 * @param string $prefix
	 *
	 * @return IQueryRow
	 * @throws MemberNotFoundException
	 */
	public function importFromDatabase(array $data, string $prefix = ''): IQueryRow {
	}


	/**
	 * @return string[]
	 * @throws UnknownInterfaceException
	 */
	public function jsonSerialize(): array {
	}


	/**
	 * @param int $level
	 *
	 * @return int
	 * @throws ParseMemberLevelException
	 */
	public static function parseLevelInt(int $level): int {
	}


	/**
	 * @param string $levelString
	 *
	 * @return int
	 * @throws ParseMemberLevelException
	 */
	public static function parseLevelString(string $levelString): int {
	}

	/**
	 * @param string $typeString
	 *
	 * @return int
	 * @throws UserTypeNotFoundException
	 */
	public static function parseTypeString(string $typeString): int {
	}
}
