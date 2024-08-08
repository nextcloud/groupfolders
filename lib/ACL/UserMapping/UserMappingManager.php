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
	private $groupManager;
	private $userManager;

	public function __construct(IGroupManager $groupManager, IUserManager $userManager) {
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}

	public function getMappingsForUser(IUser $user, bool $userAssignable = true): array {
		$groupMappings = array_values(array_map(function (IGroup $group) {
			return new UserMapping('group', $group->getGID(), $group->getDisplayName());
		}, $this->groupManager->getUserGroups($user)));

		return array_merge([
			new UserMapping('user', $user->getUID(), $user->getDisplayName()),
		], $groupMappings);
	}

	public function mappingFromId(string $type, string $id): ?IUserMapping {
		$mappingObject = ($type === 'group' ? $this->groupManager : $this->userManager)->get($id);
		if ($mappingObject) {
			$displayName = $mappingObject->getDisplayName();
			return new UserMapping($type, $id, $displayName);
		} else {
			return null;
		}
	}
}
