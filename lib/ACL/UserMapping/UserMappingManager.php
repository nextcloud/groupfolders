<?php

declare(strict_types=1);
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

use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Probes\CircleProbe;
use OCP\AutoloadNotAllowedException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;

class UserMappingManager implements IUserMappingManager {
	public function __construct(
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	public function getMappingsForUser(IUser $user, bool $userAssignable = true): array {
		$groupMappings = array_values(array_map(function (IGroup $group) {
			return new UserMapping('group', $group->getGID(), $group->getDisplayName());
		}, $this->groupManager->getUserGroups($user)));
		$circleMappings = array_values(array_map(fn (Circle $circle): UserMapping => new UserMapping('circle', $circle->getSingleId(), $circle->getDisplayName()), $this->getUserCircles($user->getUID())));

		return array_merge([
			new UserMapping('user', $user->getUID(), $user->getDisplayName()),
		], $groupMappings, $circleMappings);
	}

	public function mappingFromId(string $type, string $id): ?IUserMapping {
		switch ($type) {
			case 'group':
				$displayName = $this->groupManager->get($id)?->getDisplayName();
				break;
			case 'user':
				$displayName = $this->userManager->get($id)?->getDisplayName();
				break;
			case 'circle':
				$displayName = $this->getCircle($id)?->getDisplayName();
				break;
			default:
				return null;
		}
		if ($displayName === null) {
			return null;
		}

		return new UserMapping($type, $id, $displayName);
	}



	/**
	 * returns the Circle from its single Id, or NULL if not available
	 */
	private function getCircle(string $groupId): ?Circle {
		$circlesManager = $this->getCirclesManager();
		if ($circlesManager === null) {
			return null;
		}

		$circlesManager->startSuperSession();
		$probe = new CircleProbe();
		$probe->includeSystemCircles();
		$probe->includeSingleCircles();
		try {
			return $circlesManager->getCircle($groupId, $probe);
		} catch (CircleNotFoundException) {
		} catch (\Exception $e) {
			$this->logger->warning('', ['exception' => $e]);
		} finally {
			$circlesManager->stopSession();
		}

		return null;
	}

	/**
	 * returns list of circles a user is member of
	 */
	private function getUserCircles(string $userId): array {
		$circlesManager = $this->getCirclesManager();
		if ($circlesManager === null) {
			return [];
		}

		$circlesManager->startSession($circlesManager->getLocalFederatedUser($userId));
		try {
			return $circlesManager->probeCircles();
		} catch (\Exception $e) {
			$this->logger->warning('', ['exception' => $e]);
		} finally {
			$circlesManager->stopSession();
		}

		return [];
	}

	public function getCirclesManager(): ?CirclesManager {
		try {
			return Server::get(CirclesManager::class);
		} catch (ContainerExceptionInterface|AutoloadNotAllowedException) {
			return null;
		}
	}
}
