<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Settings;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * @template-extends QBMapper<AuthorizedGroup>
 */
class AuthorizedGroupMapper extends QBMapper {
	public function __construct(IDBConnection $db)
 {
 }

	/**
	 * @throws Exception
	 */
	public function findAllClassesForUser(IUser $user): array
 {
 }

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function find(int $id): AuthorizedGroup
 {
 }

	/**
	 * Get all the authorizations stored in the database.
	 *
	 * @return AuthorizedGroup[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll(): array
 {
 }

	public function findByGroupIdAndClass(string $groupId, string $class)
 {
 }

	/**
	 * @return Entity[]
	 * @throws \OCP\DB\Exception
	 */
	public function findExistingGroupsForClass(string $class): array
 {
 }

	/**
	 * @throws Exception
	 */
	public function removeGroup(string $gid)
 {
 }
}
