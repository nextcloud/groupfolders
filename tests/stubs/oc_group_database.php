<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Group;

use OC\User\LazyUser;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Group\Backend\ABackend;
use OCP\Group\Backend\IAddToGroupBackend;
use OCP\Group\Backend\IBatchMethodsBackend;
use OCP\Group\Backend\ICountDisabledInGroup;
use OCP\Group\Backend\ICountUsersBackend;
use OCP\Group\Backend\ICreateNamedGroupBackend;
use OCP\Group\Backend\IDeleteGroupBackend;
use OCP\Group\Backend\IGetDisplayNameBackend;
use OCP\Group\Backend\IGroupDetailsBackend;
use OCP\Group\Backend\INamedBackend;
use OCP\Group\Backend\IRemoveFromGroupBackend;
use OCP\Group\Backend\ISearchableGroupBackend;
use OCP\Group\Backend\ISetDisplayNameBackend;
use OCP\IDBConnection;
use OCP\IUserManager;

/**
 * Class for group management in a SQL Database (e.g. MySQL, SQLite)
 */
class Database extends ABackend implements
	IAddToGroupBackend,
	ICountDisabledInGroup,
	ICountUsersBackend,
	ICreateNamedGroupBackend,
	IDeleteGroupBackend,
	IGetDisplayNameBackend,
	IGroupDetailsBackend,
	IRemoveFromGroupBackend,
	ISetDisplayNameBackend,
	ISearchableGroupBackend,
	IBatchMethodsBackend,
	INamedBackend {
	/**
	 * \OC\Group\Database constructor.
	 *
	 * @param IDBConnection|null $dbConn
	 */
	public function __construct(
		private ?IDBConnection $dbConn = null,
	) {
	}

	public function createGroup(string $name): ?string
 {
 }

	/**
	 * delete a group
	 * @param string $gid gid of the group to delete
	 * @return bool
	 *
	 * Deletes a group and removes it from the group_user-table
	 */
	public function deleteGroup(string $gid): bool
 {
 }

	/**
	 * is user in group?
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 *
	 * Checks whether the user is member of a group or not.
	 */
	public function inGroup($uid, $gid)
 {
 }

	/**
	 * Add a user to a group
	 * @param string $uid Name of the user to add to group
	 * @param string $gid Name of the group in which add the user
	 * @return bool
	 *
	 * Adds a user to a group.
	 */
	public function addToGroup(string $uid, string $gid): bool
 {
 }

	/**
	 * Removes a user from a group
	 * @param string $uid Name of the user to remove from group
	 * @param string $gid Name of the group from which remove the user
	 * @return bool
	 *
	 * removes the user from a group.
	 */
	public function removeFromGroup(string $uid, string $gid): bool
 {
 }

	/**
	 * Get all groups a user belongs to
	 * @param string $uid Name of the user
	 * @return list<string> an array of group names
	 *
	 * This function fetches all groups a user belongs to. It does not check
	 * if the user exists at all.
	 */
	public function getUserGroups($uid)
 {
 }

	/**
	 * get a list of all groups
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of group names
	 *
	 * Returns a list with all groups
	 */
	public function getGroups(string $search = '', int $limit = -1, int $offset = 0)
 {
 }

	/**
	 * check if a group exists
	 * @param string $gid
	 * @return bool
	 */
	public function groupExists($gid)
 {
 }

	/**
	 * {@inheritdoc}
	 */
	public function groupsExists(array $gids): array
 {
 }

	/**
	 * Get a list of all users in a group
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array<int,string> an array of user ids
	 */
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0): array
 {
 }

	public function searchInGroup(string $gid, string $search = '', int $limit = -1, int $offset = 0): array
 {
 }

	/**
	 * get the number of all users matching the search string in a group
	 * @param string $gid
	 * @param string $search
	 * @return int
	 */
	public function countUsersInGroup(string $gid, string $search = ''): int
 {
 }

	/**
	 * get the number of disabled users in a group
	 *
	 * @param string $search
	 *
	 * @return int
	 */
	public function countDisabledInGroup(string $gid): int
 {
 }

	public function getDisplayName(string $gid): string
 {
 }

	public function getGroupDetails(string $gid): array
 {
 }

	/**
	 * {@inheritdoc}
	 */
	public function getGroupsDetails(array $gids): array
 {
 }

	public function setDisplayName(string $gid, string $displayName): bool
 {
 }

	/**
	 * Backend name to be shown in group management
	 * @return string the name of the backend to be shown
	 * @since 21.0.0
	 */
	public function getBackendName(): string
 {
 }
}
