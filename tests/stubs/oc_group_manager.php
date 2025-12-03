<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Group;

use OC\Hooks\PublicEmitter;
use OC\Settings\AuthorizedGroupMapper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Backend\IBatchMethodsBackend;
use OCP\Group\Backend\ICreateNamedGroupBackend;
use OCP\Group\Backend\IGroupDetailsBackend;
use OCP\Group\Events\BeforeGroupCreatedEvent;
use OCP\Group\Events\GroupCreatedEvent;
use OCP\GroupInterface;
use OCP\ICacheFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Security\Ip\IRemoteAddress;
use Psr\Log\LoggerInterface;
use function is_string;

/**
 * Class Manager
 *
 * Hooks available in scope \OC\Group:
 * - preAddUser(\OC\Group\Group $group, \OC\User\User $user)
 * - postAddUser(\OC\Group\Group $group, \OC\User\User $user)
 * - preRemoveUser(\OC\Group\Group $group, \OC\User\User $user)
 * - postRemoveUser(\OC\Group\Group $group, \OC\User\User $user)
 * - preDelete(\OC\Group\Group $group)
 * - postDelete(\OC\Group\Group $group)
 * - preCreate(string $groupId)
 * - postCreate(\OC\Group\Group $group)
 *
 * @package OC\Group
 */
class Manager extends PublicEmitter implements IGroupManager {
	private const MAX_GROUP_LENGTH = 255;

	public function __construct(private \OC\User\Manager $userManager, private IEventDispatcher $dispatcher, private LoggerInterface $logger, ICacheFactory $cacheFactory, private IRemoteAddress $remoteAddress)
 {
 }

	/**
	 * Checks whether a given backend is used
	 *
	 * @param string $backendClass Full classname including complete namespace
	 * @return bool
	 */
	public function isBackendUsed($backendClass)
 {
 }

	/**
	 * @param \OCP\GroupInterface $backend
	 */
	public function addBackend($backend)
 {
 }

	public function clearBackends()
 {
 }

	/**
	 * Get the active backends
	 *
	 * @return \OCP\GroupInterface[]
	 */
	public function getBackends()
 {
 }


	protected function clearCaches()
 {
 }

	/**
	 * @param string $gid
	 * @return IGroup|null
	 */
	public function get($gid)
 {
 }

	/**
	 * @param string $gid
	 * @param string $displayName
	 * @return \OCP\IGroup|null
	 */
	protected function getGroupObject($gid, $displayName = null)
 {
 }

	/**
	 * @brief Batch method to create group objects
	 *
	 * @param list<string> $gids List of groupIds for which we want to create a IGroup object
	 * @param array<string, string> $displayNames Array containing already know display name for a groupId
	 * @return array<string, IGroup>
	 */
	protected function getGroupsObjects(array $gids, array $displayNames = []): array
 {
 }

	/**
	 * @param string $gid
	 * @return bool
	 */
	public function groupExists($gid)
 {
 }

	/**
	 * @param string $gid
	 * @return IGroup|null
	 */
	public function createGroup($gid)
 {
 }

	/**
	 * @param string $search
	 * @param ?int $limit
	 * @param ?int $offset
	 * @return \OC\Group\Group[]
	 */
	public function search(string $search, ?int $limit = null, ?int $offset = 0)
 {
 }

	/**
	 * @param IUser|null $user
	 * @return \OC\Group\Group[]
	 */
	public function getUserGroups(?IUser $user = null)
 {
 }

	/**
	 * @param string $uid the user id
	 * @return \OC\Group\Group[]
	 */
	public function getUserIdGroups(string $uid): array
 {
 }

	/**
	 * Checks if a userId is in the admin group
	 *
	 * @param string $userId
	 * @return bool if admin
	 */
	public function isAdmin($userId)
 {
 }

	public function isDelegatedAdmin(string $userId): bool
 {
 }

	/**
	 * Checks if a userId is in a group
	 *
	 * @param string $userId
	 * @param string $group
	 * @return bool if in group
	 */
	public function isInGroup($userId, $group)
 {
 }

	/**
	 * get a list of group ids for a user
	 *
	 * @param IUser $user
	 * @return string[] with group ids
	 */
	public function getUserGroupIds(IUser $user): array
 {
 }

	/**
	 * @param string $groupId
	 * @return ?string
	 */
	public function getDisplayName(string $groupId): ?string
 {
 }

	/**
	 * get an array of groupid and displayName for a user
	 *
	 * @param IUser $user
	 * @return array ['displayName' => displayname]
	 */
	public function getUserGroupNames(IUser $user)
 {
 }

	/**
	 * get a list of all display names in a group
	 *
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of display names (value) and user ids (key)
	 */
	public function displayNamesInGroup($gid, $search = '', $limit = -1, $offset = 0)
 {
 }

	/**
	 * @return \OC\SubAdmin
	 */
	public function getSubAdmin()
 {
 }
}
