<?php

declare(strict_types=1);

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
use Throwable;

/**
 * @psalm-api - we cannot use final as this will break unit tests
 */
readonly class AuthorizedGroupService {
	public function __construct(
		private AuthorizedGroupMapper $mapper,
	) {
	}

	/**
	 * @return AuthorizedGroup[]
	 * @throws Exception
	 */
	public function findAll(): array
    {
    }

	/**
	 * Find AuthorizedGroup by id.
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): ?AuthorizedGroup
    {
    }

	/**
	 * Create a new AuthorizedGroup
	 *
	 * @throws Exception
	 * @throws ConflictException
	 * @throws MultipleObjectsReturnedException
	 */
	public function create(string $groupId, string $class): AuthorizedGroup
    {
    }

	/**
	 * @throws NotFoundException
	 * @throws Throwable
	 */
	public function delete(int $id): void
    {
    }

	/**
	 * @return list<AuthorizedGroup>
	 */
	public function findExistingGroupsForClass(string $class): array
    {
    }

	/**
	 * @throws Throwable
	 * @throws NotFoundException
	 */
	public function removeAuthorizationAssociatedTo(IGroup $group): void
    {
    }
}
