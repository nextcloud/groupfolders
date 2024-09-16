<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\Connector\Sabre;

use OC\KnownUser\KnownUserService;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\Traits\PrincipalProxyTrait;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use OCP\Constants;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Share\IManager as IShareManager;
use Sabre\DAV\Exception;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

class Principal implements BackendInterface {

	public function __construct(IUserManager $userManager, IGroupManager $groupManager, IAccountManager $accountManager, IShareManager $shareManager, IUserSession $userSession, IAppManager $appManager, ProxyMapper $proxyMapper, KnownUserService $knownUserService, IConfig $config, IFactory $languageFactory, string $principalPrefix = 'principals/users/')
 {
 }

	use PrincipalProxyTrait {
		getGroupMembership as protected traitGetGroupMembership;
	}

	/**
	 * Returns a list of principals based on a prefix.
	 *
	 * This prefix will often contain something like 'principals'. You are only
	 * expected to return principals that are in this base path.
	 *
	 * You are expected to return at least a 'uri' for every user, you can
	 * return any additional properties if you wish so. Common properties are:
	 *   {DAV:}displayname
	 *
	 * @param string $prefixPath
	 * @return string[]
	 */
	public function getPrincipalsByPrefix($prefixPath)
 {
 }

	/**
	 * Returns a specific principal, specified by it's path.
	 * The returned structure should be the exact same as from
	 * getPrincipalsByPrefix.
	 *
	 * @param string $path
	 * @return array
	 */
	public function getPrincipalByPath($path)
 {
 }

	/**
	 * Returns the list of groups a principal is a member of
	 *
	 * @param string $principal
	 * @param bool $needGroups
	 * @return array
	 * @throws Exception
	 */
	public function getGroupMembership($principal, $needGroups = false)
 {
 }

	/**
	 * @param string $path
	 * @param PropPatch $propPatch
	 * @return int
	 */
	public function updatePrincipal($path, PropPatch $propPatch)
 {
 }

	/**
	 * Search user principals
	 *
	 * @param array $searchProperties
	 * @param string $test
	 * @return array
	 */
	protected function searchUserPrincipals(array $searchProperties, $test = 'allof')
 {
 }

	/**
	 * @param string $prefixPath
	 * @param array $searchProperties
	 * @param string $test
	 * @return array
	 */
	public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
 {
 }

	/**
	 * @param string $uri
	 * @param string $principalPrefix
	 * @return string
	 */
	public function findByUri($uri, $principalPrefix)
 {
 }

	/**
	 * @param IUser $user
	 * @return array
	 * @throws PropertyDoesNotExistException
	 */
	protected function userToPrincipal($user)
 {
 }

	public function getPrincipalPrefix()
 {
 }

	/**
	 * @param string $circleUniqueId
	 * @return array|null
	 */
	protected function circleToPrincipal($circleUniqueId)
 {
 }

	/**
	 * Returns the list of circles a principal is a member of
	 *
	 * @param string $principal
	 * @return array
	 * @throws Exception
	 * @throws \OCP\AppFramework\QueryException
	 * @suppress PhanUndeclaredClassMethod
	 */
	public function getCircleMembership($principal): array
 {
 }

	/**
	 * Get all email addresses associated to a principal.
	 *
	 * @param array $principal Data from getPrincipal*()
	 * @return string[] All email addresses without the mailto: prefix
	 */
	public function getEmailAddressesOfPrincipal(array $principal): array
 {
 }
}
