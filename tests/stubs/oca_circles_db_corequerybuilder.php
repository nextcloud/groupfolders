<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Db;

use Doctrine\DBAL\Query\QueryBuilder;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\IFederatedModel;
use OCA\Circles\IFederatedUser;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\RemoteInstance;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Service\ConfigService;
use OCA\Circles\Tools\Db\ExtendedQueryBuilder;
use OCA\Circles\Tools\Traits\TArrayTools;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Server;

/**
 * Class CoreQueryBuilder
 *
 * @package OCA\Circles\Db
 */
class CoreQueryBuilder extends ExtendedQueryBuilder {
	use TArrayTools;


	public const SINGLE = 'a';
	public const CIRCLE = 'b';
	public const MEMBER = 'c';
	public const OWNER = 'd';
	public const FEDERATED_EVENT = 'e';
	public const REMOTE = 'f';
	public const BASED_ON = 'g';
	public const INITIATOR = 'h';
	public const DIRECT_INITIATOR = 'i';
	public const MEMBERSHIPS = 'j';
	public const CONFIG = 'k';
	public const UPSTREAM_MEMBERSHIPS = 'l';
	public const INHERITANCE_FROM = 'm';
	public const INHERITED_BY = 'n';
	public const INVITED_BY = 'o';
	public const MOUNT = 'p';
	public const MOUNTPOINT = 'q';
	public const SHARE = 'r';
	public const FILE_CACHE = 's';
	public const STORAGES = 't';
	public const TOKEN = 'u';
	public const OPTIONS = 'v';
	public const HELPER = 'w';


	public static $SQL_PATH = [
		self::SINGLE => [
			self::MEMBER
		],
		self::CIRCLE => [
			self::OPTIONS => [
			],
			self::MEMBER,
			self::OWNER => [
				self::BASED_ON
			],
			self::MEMBERSHIPS => [
				self::CONFIG
			],
			self::DIRECT_INITIATOR => [
				self::BASED_ON
			],
			self::INITIATOR => [
				self::BASED_ON,
				self::INHERITED_BY => [
					self::MEMBERSHIPS
				]
			],
			self::REMOTE => [
				self::MEMBER,
				self::CIRCLE => [
					self::OWNER
				]
			]
		],
		self::MEMBER => [
			self::MEMBERSHIPS => [
				self::CONFIG
			],
			self::INHERITANCE_FROM,
			self::CIRCLE => [
				self::OPTIONS => [
					'getData' => true
				],
				self::OWNER,
				self::MEMBERSHIPS => [
					self::CONFIG
				],
				self::DIRECT_INITIATOR,
				self::INITIATOR => [
					self::OPTIONS => [
						'minimumLevel' => Member::LEVEL_MEMBER
					],
					self::BASED_ON,
					self::INHERITED_BY => [
						self::MEMBERSHIPS
					],
					self::INVITED_BY => [
						self::OWNER,
						self::BASED_ON
					]
				]
			],
			self::BASED_ON => [
				self::OWNER,
				self::MEMBERSHIPS,
				self::INITIATOR => [
					self::BASED_ON,
					self::INHERITED_BY => [
						self::MEMBERSHIPS
					]
				]
			],
			self::REMOTE => [
				self::MEMBER,
				self::CIRCLE => [
					self::OWNER
				]
			],
			self::INVITED_BY => [
				self::OWNER,
				self::BASED_ON
			]
		],
		self::MEMBERSHIPS => [
			self::CONFIG
		],
		self::SHARE => [
			self::SHARE,
			self::TOKEN,
			self::FILE_CACHE => [
				self::STORAGES
			],
			self::UPSTREAM_MEMBERSHIPS => [
				self::MEMBERSHIPS,
				self::INHERITED_BY => [
					self::BASED_ON
				],
				self::SHARE,
			],
			self::MEMBERSHIPS => [
				self::CONFIG
			],
			self::INHERITANCE_FROM,
			self::INHERITED_BY => [
				self::BASED_ON
			],
			self::CIRCLE => [
				self::OWNER
			],
			self::INITIATOR => [
				self::BASED_ON,
				self::INHERITED_BY => [
					self::MEMBERSHIPS
				]
			]
		],
		self::REMOTE => [
			self::MEMBER
		],
		self::MOUNT => [
			self::MEMBER => [
				self::REMOTE
			],
			self::INITIATOR => [
				self::INHERITED_BY => [
					self::MEMBERSHIPS
				]
			],
			self::MOUNTPOINT,
			self::MEMBERSHIPS => [
				self::CONFIG
			]
		],
		self::HELPER => [
			self::MEMBERSHIPS => [
				self::CONFIG
			],
			self::INITIATOR => [
				self::INHERITED_BY => [
					self::MEMBERSHIPS
				]
			],
			self::CIRCLE => [
				self::OPTIONS => [
				],
				self::MEMBER,
				self::OWNER => [
					self::BASED_ON
				]
			]
		]
	];

	/**
	 * CoreQueryBuilder constructor.
	 */
	public function __construct()
 {
 }


	/**
	 * @param IFederatedModel $federatedModel
	 *
	 * @return string
	 */
	public function getInstance(IFederatedModel $federatedModel): string
 {
 }


	/**
	 * @param string $id
	 */
	public function limitToCircleId(string $id): void
 {
 }

	/**
	 * @param string $name
	 */
	public function limitToName(string $name): void
 {
 }

	/**
	 * @param string $name
	 */
	public function limitToDisplayName(string $name): void
 {
 }

	/**
	 * @param string $name
	 */
	public function limitToSanitizedName(string $name): void
 {
 }

	/**
	 * @param int $config
	 */
	public function limitToConfig(int $config): void
 {
 }

	/**
	 * @param int $source
	 */
	public function limitToSource(int $source): void
 {
 }

	/**
	 * @param int $config
	 * @param string $alias
	 */
	public function limitToConfigFlag(int $config, string $alias = ''): void
 {
 }


	/**
	 * @param string $singleId
	 */
	public function limitToSingleId(string $singleId, string $alias = ''): void
 {
 }


	/**
	 * @param string $itemId
	 */
	public function limitToItemId(string $itemId): void
 {
 }


	/**
	 * @param string $host
	 */
	public function limitToInstance(string $host): void
 {
 }


	/**
	 * @param int $userType
	 */
	public function limitToUserType(int $userType): void
 {
 }


	/**
	 * @param int $shareType
	 */
	public function limitToShareType(int $shareType): void
 {
 }


	/**
	 * @param string $shareWith
	 */
	public function limitToShareWith(string $shareWith): void
 {
 }


	/**
	 * @param int $nodeId
	 */
	public function limitToFileSource(int $nodeId): void
 {
 }

	/**
	 * @param array $files
	 */
	public function limitToFileSourceArray(array $files): void
 {
 }


	/**
	 * @param int $shareId
	 */
	public function limitToShareParent(int $shareId): void
 {
 }


	/**
	 * filter result on details (ie. displayName, Description, ...)
	 *
	 * @param Circle $circle
	 */
	public function filterCircleDetails(Circle $circle): void
 {
 }


	/**
	 * left join RemoteInstance based on a Member
	 */
	public function leftJoinRemoteInstance(string $alias): void
 {
 }


	/**
	 * @param string $alias
	 * @param RemoteInstance $remoteInstance
	 * @param bool $filterSensitiveData
	 * @param string $aliasCircle
	 *
	 * @throws RequestBuilderException
	 */
	public function limitToRemoteInstance(string $alias, RemoteInstance $remoteInstance, bool $filterSensitiveData = true, string $aliasCircle = ''): void
 {
 }


	/**
	 * Left join RemoteInstance based on an incoming request
	 *
	 * @param string $alias
	 * @param RemoteInstance $remoteInstance
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinRemoteInstanceIncomingRequest(string $alias, RemoteInstance $remoteInstance): void
 {
 }


	/**
	 * - global_scale: visibility on all Circles
	 * - trusted: visibility on all FEDERATED Circle if owner is local
	 * - external: visibility on all FEDERATED Circle if owner is local and:
	 *    - with if Circle contains at least one member from the remote instance
	 *    - one circle from the remote instance contains the local circle as member, and confirmed (using
	 *      sync locally)
	 * - passive: like external, but the members list will only contains member from the local instance and
	 * from the remote instance.
	 *
	 * @param string $alias
	 * @param bool $sensitive
	 * @param string $aliasCircle
	 *
	 * @throws RequestBuilderException
	 */
	protected function limitRemoteVisibility(string $alias, bool $sensitive, string $aliasCircle)
 {
 }


	/**
	 * @param string $alias
	 * @param Member $member
	 *
	 * @throws RequestBuilderException
	 */
	public function limitToDirectMembership(string $alias, Member $member): void
 {
 }


	/**
	 * @param string $alias
	 * @param string $aliasCircle
	 * @param FederatedUser $federatedUser
	 *
	 * @throws RequestBuilderException
	 */
	public function limitToFederatedUserMemberships(string $alias, string $aliasCircle, FederatedUser $federatedUser): void
 {
 }


	/**
	 * @param string $aliasMember
	 * @param Member $member
	 */
	public function filterDirectMembership(string $aliasMember, Member $member): void
 {
 }


	/**
	 * @param string $alias
	 * @param IFederatedUser|null $initiator
	 * @param string $field
	 * @param string $helperAlias
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinCircle(string $alias, ?IFederatedUser $initiator = null, string $field = 'circle_id', string $helperAlias = ''): void
 {
 }


	/**
	 * @param string $aliasMember
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinInvitedBy(string $aliasMember): void
 {
 }


	/**
	 * @param string $aliasMember
	 * @param IFederatedUser|null $initiator
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinBasedOn(string $aliasMember, ?IFederatedUser $initiator = null): void
 {
 }


	/**
	 * @param string $alias
	 * @param string $field
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinOwner(string $alias, string $field = 'unique_id'): void
 {
 }


	/**
	 * @param CircleProbe $probe
	 * @param string $alias
	 * @param string $field
	 */
	public function innerJoinMembership(?CircleProbe $probe, string $alias, string $field = 'unique_id'): void
 {
 }


	/**
	 * @param string $alias
	 * @param string $fieldCircleId
	 * @param string $fieldSingleId
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinMember(string $alias, string $fieldCircleId = 'circle_id', string $fieldSingleId = 'single_id'): void
 {
 }


	/**
	 * if 'getData' is true, will returns 'inheritanceBy': the Member at the end of a sub-chain of
	 * memberships (based on $field for Top Circle's singleId)
	 *
	 * @param string $alias
	 * @param string $field
	 * @param string $aliasInheritedBy
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinInheritedMembers(string $alias, string $field = '', string $aliasInheritedBy = ''): void
 {
 }


	/**
	 * @throws RequestBuilderException
	 */
	public function limitToInheritedMemberships(string $alias, string $singleId, string $field = ''): void
 {
 }


	/**
	 * limit the request to Members and Sub Members of a Circle.
	 *
	 * @param string $alias
	 * @param string $singleId
	 * @param int $level
	 *
	 * @throws RequestBuilderException
	 */
	public function limitToMembersByInheritance(string $alias, string $singleId, int $level = 0): void
 {
 }


	/**
	 * if 'getData' is true, will returns 'inheritanceFrom': the Circle-As-Member of the Top Circle
	 * that explain the membership of a Member (based on $field for singleId) to a specific Circle
	 *
	 * // TODO: returns the link/path ?
	 *
	 * @param string $alias
	 * @param string $field
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinMembersByInheritance(string $alias, string $field = ''): void
 {
 }


	/**
	 * @param string $alias
	 * @param string $token
	 *
	 * @throws RequestBuilderException
	 */
	public function limitToShareToken(string $alias, string $token): void
 {
 }

	/**
	 * @param string $alias
	 * @param string $field
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinShareToken(string $alias, string $field = ''): void
 {
 }


	/**
	 * limit the result to the point of view of a FederatedUser
	 *
	 * @param string $alias
	 * @param IFederatedUser $user
	 * @param string $field
	 * @param string $helperAlias
	 *
	 * @return ICompositeExpression
	 * @throws RequestBuilderException
	 */
	public function limitToInitiator(string $alias, IFederatedUser $user, string $field = '', string $helperAlias = ''): ICompositeExpression
 {
 }


	/**
	 * @param string $alias
	 */
	public function leftJoinCircleConfig(string $alias): void
 {
 }


	/**
	 * Left join members to filter userId as initiator.
	 *
	 * @param string $alias
	 * @param IFederatedUser $initiator
	 * @param string $field
	 * @param string $helperAlias
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinInitiator(string $alias, IFederatedUser $initiator, string $field = '', string $helperAlias = ''): void
 {
 }


	public function completeProbeWithInitiator(string $alias, string $field = 'single_id', string $helperAlias = ''): void
 {
 }

	/**
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 * @throws RequestBuilderException
	 */
	protected function limitInitiatorVisibility(string $alias): ICompositeExpression
 {
 }


	/**
	 * @param string $aliasCircle
	 * @param CircleProbe $probe
	 */
	public function filterCircles(string $aliasCircle, CircleProbe $probe): void
 {
 }


	/**
	 * Link to storage/filecache
	 *
	 * @param string $aliasShare
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinFileCache(string $aliasShare)
 {
 }


	/**
	 * @param string $aliasShare
	 * @param string $aliasShareMemberships
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinShareChild(string $aliasShare, string $aliasShareMemberships = '')
 {
 }


	/**
	 * @param string $alias
	 * @param FederatedUser $federatedUser
	 * @param bool $reshares
	 */
	public function limitToShareOwner(string $alias, FederatedUser $federatedUser, bool $reshares, int $nodeId = 0): void
 {
 }


	/**
	 * @param string $aliasMount
	 * @param string $aliasMountMemberships
	 *
	 * @throws RequestBuilderException
	 */
	public function leftJoinMountpoint(string $aliasMount, IFederatedUser $federatedUser, string $aliasMountMemberships = '')
 {
 }


	/**
	 * @param array $path
	 * @param array $options
	 *
	 * @return CoreQueryBuilder&IQueryBuilder
	 */
	public function setOptions(array $path, array $options): self
 {
 }


	/**
	 * @param string $base
	 * @param string $extension
	 * @param array|null $options
	 *
	 * @return string
	 * @throws RequestBuilderException
	 */
	public function generateAlias(string $base, string $extension, ?array &$options = []): string
 {
 }


	/**
	 * @param string $prefix
	 *
	 * @return array
	 */
	public function getAvailablePath(string $prefix): array
 {
 }


	/**
	 * @return array
	 */
	public function getSqlPath(): array
 {
 }


	/**
	 * DataProbe uses this to set which data need to be extracted, based on self::$SQL_PATH.
	 *
	 * @param string $key
	 * @param array $path
	 *
	 * @return $this
	 */
	public function setSqlPath(string $key, array $path = []): self
 {
 }

	public function resetSqlPath(): self
 {
 }
}
