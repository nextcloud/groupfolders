<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL\UserMapping;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class UserMappingManager implements IUserMappingManager {
	public function __construct(
		private IGroupManager $groupManager,
		private IUserManager $userManager,
	) {
	}

	public function getMappingsForUser(IUser $user, bool $userAssignable = true): array {
		$groupMappings = array_values(array_map(fn (IGroup $group): UserMapping => new UserMapping('group', $group->getGID(), $group->getDisplayName()), $this->groupManager->getUserGroups($user)));

		return array_merge([
			new UserMapping('user', $user->getUID(), $user->getDisplayName()),
		], $groupMappings);
	}

	public function mappingFromId(string $type, string $id): ?IUserMapping {
		$mappingObject = ($type === 'group' ? $this->groupManager : $this->userManager)->get($id);
		if ($mappingObject) {
			$displayName = $mappingObject->getDisplayName();
			/** @var 'user'|'group' $type */
			return new UserMapping($type, $id, $displayName);
		} else {
			return null;
		}
	}
}
