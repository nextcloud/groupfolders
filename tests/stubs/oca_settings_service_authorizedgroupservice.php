<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Settings\Service;

use OC\Settings\AuthorizedGroup;
use OC\Settings\AuthorizedGroupMapper;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\IGroup;

class AuthorizedGroupService {

	public function __construct(AuthorizedGroupMapper $mapper)
 {
 }

	/**
	 * @return AuthorizedGroup[]
	 */
	public function findAll(): array
 {
 }

	/**
	 * Find AuthorizedGroup by id.
	 *
	 * @param int $id
	 */
	public function find(int $id): ?AuthorizedGroup
 {
 }

	/**
	 * Create a new AuthorizedGroup
	 *
	 * @param string $groupId
	 * @param string $class
	 * @return AuthorizedGroup
	 * @throws Exception
	 */
	public function create(string $groupId, string $class): AuthorizedGroup
 {
 }

	/**
	 * @throws NotFoundException
	 */
	public function delete(int $id): void
 {
 }

	public function findExistingGroupsForClass(string $class): array
 {
 }

	public function removeAuthorizationAssociatedTo(IGroup $group): void
 {
 }
}
