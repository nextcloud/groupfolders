<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Settings;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Server;

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
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(int $id): AuthorizedGroup
 {
 }

	/**
	 * Get all the authorizations stored in the database.
	 *
	 * @return AuthorizedGroup[]
	 * @throws Exception
	 */
	public function findAll(): array
 {
 }

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByGroupIdAndClass(string $groupId, string $class): AuthorizedGroup
 {
 }

	/**
	 * @return list<AuthorizedGroup>
	 * @throws Exception
	 */
	public function findExistingGroupsForClass(string $class): array
 {
 }

	/**
	 * @throws Exception
	 */
	public function removeGroup(string $gid): void
 {
 }
}
