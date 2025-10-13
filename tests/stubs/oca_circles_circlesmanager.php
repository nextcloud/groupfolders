<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles;

use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Exceptions\ContactAddressBookNotFoundException;
use OCA\Circles\Exceptions\ContactFormatException;
use OCA\Circles\Exceptions\ContactNotFoundException;
use OCA\Circles\Exceptions\FederatedEventException;
use OCA\Circles\Exceptions\FederatedItemException;
use OCA\Circles\Exceptions\FederatedUserException;
use OCA\Circles\Exceptions\FederatedUserNotFoundException;
use OCA\Circles\Exceptions\InitiatorNotConfirmedException;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\InvalidIdException;
use OCA\Circles\Exceptions\MemberNotFoundException;
use OCA\Circles\Exceptions\MembershipNotFoundException;
use OCA\Circles\Exceptions\OwnerNotFoundException;
use OCA\Circles\Exceptions\RemoteInstanceException;
use OCA\Circles\Exceptions\RemoteNotFoundException;
use OCA\Circles\Exceptions\RemoteResourceNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\SingleCircleNotFoundException;
use OCA\Circles\Exceptions\UnknownRemoteException;
use OCA\Circles\Exceptions\UserTypeNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Membership;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Model\Probes\DataProbe;
use OCA\Circles\Service\CircleService;
use OCA\Circles\Service\ConfigService;
use OCA\Circles\Service\FederatedUserService;
use OCA\Circles\Service\MemberService;
use OCA\Circles\Service\MembershipService;
use OCA\Circles\Tools\Exceptions\InvalidItemException;

/**
 * Class CirclesManager
 *
 * @package OCA\Circles
 */
class CirclesManager {
	/**
	 * CirclesManager constructor.
	 *
	 * @param FederatedUserService $federatedUserService
	 * @param CircleService $circleService
	 * @param MemberService $memberService
	 * @param MembershipService $membershipService
	 * @param ConfigService $configService
	 * @param CirclesQueryHelper $circlesQueryHelper
	 */
	public function __construct(FederatedUserService $federatedUserService, CircleService $circleService, MemberService $memberService, MembershipService $membershipService, ConfigService $configService, CirclesQueryHelper $circlesQueryHelper)
 {
 }


	/**
	 * @param string $federatedId
	 * @param int $type
	 *
	 * @return FederatedUser
	 * @throws CircleNotFoundException
	 * @throws FederatedItemException
	 * @throws FederatedUserException
	 * @throws FederatedUserNotFoundException
	 * @throws InvalidIdException
	 * @throws MemberNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 * @throws UnknownRemoteException
	 * @throws UserTypeNotFoundException
	 */
	public function getFederatedUser(string $federatedId, int $type = Member::TYPE_SINGLE): FederatedUser
 {
 }

	/**
	 * @param string $userId
	 *
	 * @return FederatedUser
	 * @throws CircleNotFoundException
	 * @throws FederatedItemException
	 * @throws FederatedUserException
	 * @throws FederatedUserNotFoundException
	 * @throws InvalidIdException
	 * @throws MemberNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 * @throws UnknownRemoteException
	 * @throws UserTypeNotFoundException
	 */
	public function getLocalFederatedUser(string $userId): FederatedUser
 {
 }


	/**
	 * @throws FederatedUserNotFoundException
	 * @throws SingleCircleNotFoundException
	 * @throws RequestBuilderException
	 * @throws InvalidIdException
	 * @throws FederatedUserException
	 */
	public function startSession(?FederatedUser $federatedUser = null, bool $forceSync = false): void
 {
 }

	/**
	 *
	 */
	public function startSuperSession(bool $forceSync = false): void
 {
 }


	/**
	 * @param string $appId
	 * @param int $appSerial
	 *
	 * @throws ContactAddressBookNotFoundException
	 * @throws ContactFormatException
	 * @throws ContactNotFoundException
	 * @throws FederatedUserException
	 * @throws InvalidIdException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 */
	public function startAppSession(string $appId, int $appSerial = Member::APP_DEFAULT): void
 {
 }

	/**
	 * $userId - userId to emulate as initiator (can be empty)
	 * $userType - specify if userIs not a singleId
	 * $circleId - if no userId specified, will use the owner of the Circle as initiator
	 *
	 * @param string $userId
	 * @param int $userType
	 * @param string $circleId
	 *
	 * @throws CircleNotFoundException
	 * @throws FederatedItemException
	 * @throws FederatedUserException
	 * @throws FederatedUserNotFoundException
	 * @throws InvalidIdException
	 * @throws MemberNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 * @throws UnknownRemoteException
	 * @throws UserTypeNotFoundException
	 */
	public function startOccSession(string $userId, int $userType = Member::TYPE_SINGLE, string $circleId = ''): void
 {
 }


	/**
	 *
	 */
	public function stopSession(): void
 {
 }


	/**
	 * @return IFederatedUser
	 * @throws FederatedUserNotFoundException
	 */
	public function getCurrentFederatedUser(): IFederatedUser
 {
 }


	/**
	 * @return CirclesQueryHelper
	 */
	public function getQueryHelper(): CirclesQueryHelper
 {
 }


	/**
	 * @param string $name
	 * @param FederatedUser|null $owner
	 * @param bool $personal
	 * @param bool $local
	 *
	 * @return Circle
	 * @throws FederatedEventException
	 * @throws InitiatorNotConfirmedException
	 * @throws FederatedItemException
	 * @throws InitiatorNotFoundException
	 * @throws InvalidItemException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function createCircle(string $name, ?FederatedUser $owner = null, bool $personal = false, bool $local = false): Circle
 {
 }


	/**
	 * @param string $singleId
	 *
	 * @throws CircleNotFoundException
	 * @throws FederatedEventException
	 * @throws FederatedItemException
	 * @throws InitiatorNotConfirmedException
	 * @throws InitiatorNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function destroyCircle(string $singleId): void
 {
 }


	/**
	 * WARNING: This method is not using Cached Memberships meaning that the request can be heavy and should
	 * only be used if probeCircles() does not fit your need.
	 *
	 * Always prefer probeCircles();
	 *
	 * returns available Circles to the current session.
	 *
	 * @see probeCircles()
	 *
	 * @return Circle[]
	 * @throws InitiatorNotFoundException
	 * @throws RequestBuilderException
	 */
	public function getCircles(?CircleProbe $probe = null, bool $refreshCache = false): array
 {
 }


	/**
	 * @param string $singleId
	 * @param CircleProbe|null $probe
	 *
	 * @return Circle
	 * @throws CircleNotFoundException
	 * @throws InitiatorNotFoundException
	 * @throws RequestBuilderException
	 */
	public function getCircle(string $singleId, ?CircleProbe $probe = null): Circle
 {
 }

	/**
	 * better than using getCircle() if only interested in teams current user is member of
	 *
	 * @since 33.0.0
	 */
	public function probeCircle(string $singleId, ?CircleProbe $probe = null, ?DataProbe $dataProbe = null): Circle
 {
 }

	/**
	 * get details from a list of circles the current user is a member of
	 *
	 * @since 33.0.0
	 */
	public function getCirclesByIds(array $ids, ?DataProbe $dataProbe = null): array
 {
 }


	/**
	 * @param Circle $circle
	 *
	 * @throws CircleNotFoundException
	 * @throws FederatedEventException
	 * @throws FederatedItemException
	 * @throws InitiatorNotConfirmedException
	 * @throws InitiatorNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function updateConfig(Circle $circle): void
 {
 }


	/**
	 * @param string $circleId
	 * @param bool $enabled
	 *
	 * @throws CircleNotFoundException
	 * @throws FederatedEventException
	 * @throws FederatedItemException
	 * @throws FederatedUserException
	 * @throws InitiatorNotConfirmedException
	 * @throws InitiatorNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function flagAsAppManaged(string $circleId, bool $enabled = true): void
 {
 }


	/**
	 * @param string $circleId
	 * @param FederatedUser $federatedUser
	 *
	 * @return Member
	 * @throws CircleNotFoundException
	 * @throws ContactAddressBookNotFoundException
	 * @throws ContactFormatException
	 * @throws ContactNotFoundException
	 * @throws FederatedEventException
	 * @throws FederatedItemException
	 * @throws FederatedUserException
	 * @throws InitiatorNotConfirmedException
	 * @throws InitiatorNotFoundException
	 * @throws InvalidIdException
	 * @throws InvalidItemException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 * @throws UnknownRemoteException
	 */
	public function addMember(string $circleId, FederatedUser $federatedUser): Member
 {
 }


	/**
	 * @param string $memberId
	 * @param int $level
	 *
	 * @return Member
	 * @throws FederatedEventException
	 * @throws FederatedItemException
	 * @throws InitiatorNotConfirmedException
	 * @throws InitiatorNotFoundException
	 * @throws InvalidItemException
	 * @throws MemberNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function levelMember(string $memberId, int $level): Member
 {
 }


	/**
	 * @param string $memberId
	 *
	 * @throws FederatedEventException
	 * @throws FederatedItemException
	 * @throws InitiatorNotConfirmedException
	 * @throws InitiatorNotFoundException
	 * @throws MemberNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws UnknownRemoteException
	 */
	public function removeMember(string $memberId): void
 {
 }


	/**
	 * @param string $circleId
	 * @param string $singleId
	 * @param bool $detailed
	 *
	 * @return Membership
	 * @throws MembershipNotFoundException
	 * @throws RequestBuilderException
	 */
	public function getLink(string $circleId, string $singleId, bool $detailed = false): Membership
 {
 }


	/**
	 * @param IEntity $circle
	 *
	 * @return string
	 */
	public function getDefinition(IEntity $circle): string
 {
 }


	/**
	 * Returns data about Circles based on cached Memberships.
	 * Meaning that only Circles the current user is a member will be returned.
	 *
	 * CircleProbe is used to filter Circles to be returned by the method.
	 * DataProbe is used to add details to returned Circles.
	 *
	 * @param CircleProbe|null $circleProbe
	 * @param DataProbe|null $dataProbe
	 *
	 * @return array
	 * @throws InitiatorNotFoundException
	 * @throws RequestBuilderException
	 */
	public function probeCircles(?CircleProbe $circleProbe = null, ?DataProbe $dataProbe = null): array
 {
 }


	/**
	 * WIP
	 *
	 * @param string $circleId
	 * @param string $singleId
	 *
	 * @return Member
	 * @throws InitiatorNotFoundException
	 * @throws MemberNotFoundException
	 * @throws RequestBuilderException
	 */
	//	public function getMember(string $circleId, string $singleId): Member {
	//		$this->federatedUserService->bypassCurrentUserCondition(true);
	//		$this->memberService->getMemberById($circleId, $singleId);
	//	}


	/**
	 * WIP
	 *
	 * @param string $memberId
	 *
	 * @return Member
	 */
	//	public function getMemberById(string $memberId): Member {
}
