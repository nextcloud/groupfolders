<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Model;

use DateTime;
use JsonSerializable;
use OCA\Circles\Db\CircleRequest;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Exceptions\FederatedItemException;
use OCA\Circles\Exceptions\MemberHelperException;
use OCA\Circles\Exceptions\MemberLevelException;
use OCA\Circles\Exceptions\MembershipNotFoundException;
use OCA\Circles\Exceptions\OwnerNotFoundException;
use OCA\Circles\Exceptions\RemoteInstanceException;
use OCA\Circles\Exceptions\RemoteNotFoundException;
use OCA\Circles\Exceptions\RemoteResourceNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\UnknownRemoteException;
use OCA\Circles\IEntity;
use OCA\Circles\Model\Helpers\MemberHelper;
use OCA\Circles\Tools\Db\IQueryRow;
use OCA\Circles\Tools\Exceptions\InvalidItemException;
use OCA\Circles\Tools\IDeserializable;
use OCA\Circles\Tools\Traits\TArrayTools;
use OCA\Circles\Tools\Traits\TDeserialize;
use OCP\Security\IHasher;

/**
 * Class Circle
 *
 * ** examples of use of bitwise flags for members management:
 *      CFG_OPEN, CFG_REQUEST, CFG_INVITE, CFG_FRIEND
 *
 * - CFG_OPEN                             => everyone can enter. moderator can add members.
 * - CFG_OPEN | CFG_REQUEST               => anyone can initiate a request to join the circle, moderator can
 *                                           add members
 * - CFG_OPEN | CFG_INVITE                => every one can enter, moderator must send invitation.
 * - CFG_OPEN | CFG_INVITE | CFG_REQUEST  => every one send a request, moderator must send invitation.
 * - CFG_OPEN | CFG_FRIEND                => useless
 * - CFG_OPEN | CFG_FRIEND | *            => useless
 *
 * - CFG_CIRCLE                           => no one can enter, moderator can add members.
 *                                           default config, this is only for code readability.
 * - CFG_INVITE                           => no one can enter, moderator must send invitation.
 * - CFG_FRIEND                           => no one can enter, but all members can add new member.
 * - CFG_REQUEST                          => useless (use CFG_OPEN | CFG_REQUEST)
 * - CFG_FRIEND | CFG_REQUEST             => no one can join the circle, but all members can request a
 *                                           moderator to accept new member
 * - CFG_FRIEND | CFG_INVITE              => no one can join the circle, but all members can add new member.
 *                                           An invitation will be generated
 * - CFG_FRIEND | CFG_INVITE | CFG_REQUEST  => no one can join the circle, but all members can request a
 *                                             moderator to accept new member. An invitation will be generated
 *
 * @package OCA\Circles\Model
 */
class Circle extends ManagedModel implements IEntity, IDeserializable, IQueryRow, JsonSerializable {
	use TArrayTools;
	use TDeserialize;

	public const FLAGS_SHORT = 1;
	public const FLAGS_LONG = 2;

	// specific value
	public const CFG_CIRCLE = 0;        // only for code readability. Circle is locked by default.
	public const CFG_SINGLE = 1;        // Circle with only one single member.
	public const CFG_PERSONAL = 2;      // Personal circle, only the owner can see it.

	// bitwise
	public const CFG_SYSTEM = 4;            // System Circle (not managed by the official front-end). Meaning some config are limited
	public const CFG_VISIBLE = 8;           // Visible to everyone, if not visible, people have to know its name to be able to find it
	public const CFG_OPEN = 16;             // Circle is open, people can join
	public const CFG_INVITE = 32;           // Adding a member generate an invitation that needs to be accepted
	public const CFG_REQUEST = 64;          // Request to join Circles needs to be confirmed by a moderator
	public const CFG_FRIEND = 128;          // Members of the circle can invite their friends
	public const CFG_PROTECTED = 256;       // Password protected to join/request
	public const CFG_NO_OWNER = 512;        // no owner, only members
	public const CFG_HIDDEN = 1024;         // hidden from listing, but available as a share entity
	public const CFG_BACKEND = 2048;            // Fully hidden, only backend Circles
	public const CFG_LOCAL = 4096;              // Local even on GlobalScale
	public const CFG_ROOT = 8192;               // Circle cannot be inside another Circle
	public const CFG_CIRCLE_INVITE = 16384;     // Circle must confirm when invited in another circle
	public const CFG_FEDERATED = 32768;         // Federated
	public const CFG_MOUNTPOINT = 65536;        // Generate a Files folder for this Circle
	public const CFG_APP = 131072;          // Some features are not available to the OCS API (ie. destroying Circle)
	public static $DEF_CFG_MAX = 262143;


	/**
	 * Note: When editing those values, update lib/Application/Capabilities.php
	 *
	 * @see Capabilities::getCapabilitiesCircleConstants()
	 * @var array
	 */
	public static $DEF_CFG = [
		1 => 'S|Single',
		2 => 'P|Personal',
		4 => 'Y|System',
		8 => 'V|Visible',
		16 => 'O|Open',
		32 => 'I|Invite',
		64 => 'JR|Join Request',
		128 => 'F|Friends',
		256 => 'PP|Password Protected',
		512 => 'NO|No Owner',
		1024 => 'H|Hidden',
		2048 => 'T|Backend',
		4096 => 'L|Local',
		8192 => 'T|Root',
		16384 => 'CI|Circle Invite',
		32768 => 'F|Federated',
		65536 => 'M|Nountpoint',
		131072 => 'A|App'
	];


	/**
	 * Note: When editing those values, update lib/Application/Capabilities.php
	 *
	 * @see Capabilities::getCapabilitiesCircleConstants()
	 * @var array
	 */
	public static $DEF_SOURCE = [
		1 => 'Nextcloud Account',
		2 => 'Nextcloud Group',
		4 => 'Email Address',
		8 => 'Contact',
		16 => 'Circle',
		10000 => 'Nextcloud App',
		10001 => 'Circles App',
		10002 => 'Admin Command Line',
		11000 => '3rd party app',
		11010 => 'Collectives App'
	];


	public static $DEF_CFG_CORE_FILTER = [
		1,
		2,
		4
	];

	public static $DEF_CFG_SYSTEM_FILTER = [
		512,
		1024,
		2048
	];


	/**
	 * Circle constructor.
	 */
	public function __construct() {
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
	 * @return string
	 * @deprecated - removed in NC23
	 */
	public function getUniqueId(): string
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
	 * @param int $flag
	 * @param int $test
	 *
	 * @return bool
	 */
	public function isConfig(int $flag, int $test = 0): bool
 {
 }

	/**
	 * @param int $flag
	 */
	public function addConfig(int $flag): void
 {
 }

	/**
	 * @param int $flag
	 */
	public function remConfig(int $flag): void
 {
 }


	/**
	 * @param string $name
	 *
	 * @return self
	 */
	public function setName(string $name): self
 {
 }

	/**
	 * @return string
	 */
	public function getName(): string
 {
 }


	/**
	 * @param string $displayName
	 *
	 * @return self
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
	 * @param string $sanitizedName
	 *
	 * @return Circle
	 */
	public function setSanitizedName(string $sanitizedName): self
 {
 }

	/**
	 * @return string
	 */
	public function getSanitizedName(): string
 {
 }


	/**
	 * @param int $source
	 *
	 * @return Circle
	 */
	public function setSource(int $source): self
 {
 }

	/**
	 * @return int
	 */
	public function getSource(): int
 {
 }


	/**
	 * @param ?Member $owner
	 *
	 * @return self
	 */
	public function setOwner(?Member $owner): self
 {
 }

	/**
	 * @return Member
	 */
	public function getOwner(): Member
 {
 }

	/**
	 * @return bool
	 */
	public function hasOwner(): bool
 {
 }


	/**
	 * @return bool
	 */
	public function hasMembers(): bool
 {
 }

	/**
	 * @param Member[] $members
	 *
	 * @return self
	 */
	public function setMembers(array $members): self
 {
 }

	/**
	 * @return Member[]
	 */
	public function getMembers(): array
 {
 }


	/**
	 * @param array $members
	 * @param bool $detailed
	 *
	 * @return self
	 */
	public function setInheritedMembers(array $members, bool $detailed): self
 {
 }

	/**
	 * @param Member[] $members
	 *
	 * @return Circle
	 */
	public function addInheritedMembers(array $members): self
 {
 }


	/**
	 * if $remote is true, it will returns also details on inherited members from remote+locals Circles.
	 * This should be used only if extra details are required (mail address ?) as it will send a request to
	 * the remote instance if the circleId is not locally known.
	 * because of the resource needed to retrieve this data, $remote=true should not be used on main process !
	 *
	 * @param bool $detailed
	 * @param bool $remote
	 *
	 * @return Member[]
	 * @throws FederatedItemException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function getInheritedMembers(bool $detailed = false, bool $remote = false): array
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
	 * @param Member|null $initiator
	 *
	 * @return Circle
	 */
	public function setInitiator(?Member $initiator): self
 {
 }

	/**
	 * @return Member
	 */
	public function getInitiator(): Member
 {
 }

	/**
	 * @return bool
	 */
	public function hasInitiator(): bool
 {
 }

	/**
	 * @param Member|null $directInitiator
	 *
	 * @return $this
	 */
	public function setDirectInitiator(?Member $directInitiator): self
 {
 }


	/**
	 * @param string $instance
	 *
	 * @return Circle
	 */
	public function setInstance(string $instance): self
 {
 }

	/**
	 * @return string
	 * @throws OwnerNotFoundException
	 */
	public function getInstance(): string
 {
 }


	/**
	 * @return bool
	 * @throws OwnerNotFoundException
	 */
	public function isLocal(): bool
 {
 }


	/**
	 * @param int $population
	 *
	 * @return Circle
	 */
	public function setPopulation(int $population): self
 {
 }

	/**
	 * @return int
	 */
	public function getPopulation(): int
 {
 }


	/**
	 * @param int $population
	 *
	 * @return Circle
	 */
	public function setPopulationInherited(int $population): self
 {
 }

	/**
	 * @return int
	 */
	public function getPopulationInherited(): int
 {
 }


	/**
	 * @param array $settings
	 *
	 * @return self
	 */
	public function setSettings(array $settings): self
 {
 }

	/**
	 * @return array
	 */
	public function getSettings(): array
 {
 }


	/**
	 * @param string $description
	 *
	 * @return self
	 */
	public function setDescription(string $description): self
 {
 }

	/**
	 * @return string
	 */
	public function getDescription(): string
 {
 }


	/**
	 * @return string
	 */
	public function getUrl(): string
 {
 }


	/**
	 * @param int $contactAddressBook
	 *
	 * @return self
	 */
	public function setContactAddressBook(int $contactAddressBook): self
 {
 }

	/**
	 * @return int
	 */
	public function getContactAddressBook(): int
 {
 }


	/**
	 * @param string $contactGroupName
	 *
	 * @return self
	 */
	public function setContactGroupName(string $contactGroupName): self
 {
 }

	/**
	 * @return string
	 */
	public function getContactGroupName(): string
 {
 }


	/**
	 * @param int $creation
	 *
	 * @return self
	 */
	public function setCreation(int $creation): self
 {
 }

	/**
	 * @return int
	 */
	public function getCreation(): int
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
	 * @return array
	 * @throws FederatedItemException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function jsonSerialize(): array
 {
 }


	/**
	 * @param array $data
	 * @param string $prefix
	 *
	 * @return IQueryRow
	 * @throws CircleNotFoundException
	 */
	public function importFromDatabase(array $data, string $prefix = ''): IQueryRow
 {
 }


	/**
	 * @param Circle $circle
	 *
	 * @return bool
	 * @throws OwnerNotFoundException
	 */
	public function compareWith(Circle $circle): bool
 {
 }


	/**
	 * @param Circle $circle
	 * @param int $display
	 *
	 * @return array
	 */
	public static function getCircleFlags(Circle $circle, int $display = self::FLAGS_LONG): array
 {
 }
}
