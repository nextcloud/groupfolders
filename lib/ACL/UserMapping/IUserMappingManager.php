<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL\UserMapping;

use OCP\IUser;

interface IUserMappingManager {
	/**
	 * @return IUserMapping[]
	 */
	public function getMappingsForUser(IUser $user): array;

	public function mappingFromId(string $type, string $id): ?IUserMapping;

	/**
	 * Check if a user is a member of one of the provided user mappings
	 *
	 * @param IUserMapping[] $mappings
	 */
	public function userInMappings(IUser $user, array $mappings): bool;

	public function resetCache(): void;
}
