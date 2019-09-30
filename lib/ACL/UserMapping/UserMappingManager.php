<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
