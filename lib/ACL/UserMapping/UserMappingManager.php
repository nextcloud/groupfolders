<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL\UserMapping;

use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Probes\CircleProbe;
use OCP\AutoloadNotAllowedException;
use OCP\Cache\CappedMemoryCache;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;

class UserMappingManager implements IUserMappingManager {
	/** @var CappedMemoryCache<list<IUserMapping>> */
	private CappedMemoryCache $mappingsByUser;

	/** @var CappedMemoryCache<IUserMapping|false> */
	private CappedMemoryCache $mappingByKey;

	/** @var CappedMemoryCache<array<string, Circle>> */
	private CappedMemoryCache $circlesByUser;

	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly LoggerInterface $logger,
	) {
		$this->mappingsByUser = new CappedMemoryCache();
		$this->mappingByKey = new CappedMemoryCache();
		$this->circlesByUser = new CappedMemoryCache();
	}

	#[\Override]
	public function getMappingsForUser(IUser $user): array {
		$cacheKey = $user->getUID();
		$cached = $this->mappingsByUser->get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		$groupMappings = array_values(array_map(fn (IGroup $group): UserMapping => new UserMapping('group', $group->getGID(), $group->getDisplayName()), $this->groupManager->getUserGroups($user)));
		$circleMappings = array_map(fn (Circle $circle): UserMapping => new UserMapping('circle', $circle->getSingleId(), $circle->getDisplayName()), $this->getUserCircles($user->getUID()));

		$mappings = array_merge([
			new UserMapping('user', $user->getUID(), $user->getDisplayName()),
		], $groupMappings, array_values($circleMappings));

		$this->mappingsByUser->set($cacheKey, $mappings);
		foreach ($mappings as $mapping) {
			$this->mappingByKey->set($mapping->getKey(), $mapping);
		}

		return $mappings;
	}

	#[\Override]
	public function mappingFromId(string $type, string $id): ?IUserMapping {
		$cacheKey = $type . ':' . $id;
		$cached = $this->mappingByKey->get($cacheKey);
		if ($cached !== null) {
			return $cached === false ? null : $cached;
		}

		if ($type !== 'group' && $type !== 'circle' && $type !== 'user') {
			return null;
		}

		$displayName = match ($type) {
			'group' => $this->groupManager->get($id)?->getDisplayName(),
			'user' => $this->userManager->get($id)?->getDisplayName(),
			'circle' => $this->getCircle($id)?->getDisplayName(),
		};

		if ($displayName === null) {
			$this->mappingByKey->set($cacheKey, false);
			return null;
		}

		$mapping = new UserMapping($type, $id, $displayName);
		$this->mappingByKey->set($cacheKey, $mapping);
		return $mapping;
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
	 * Returns list of circles a user is member of.
	 *
	 * @return array<string, Circle>
	 */
	private function getUserCircles(string $userId): array {
		if (isset($this->circlesByUser[$userId])) {
			return $this->circlesByUser[$userId];
		}
		$circlesManager = $this->getCirclesManager();
		if ($circlesManager === null) {
			$this->circlesByUser[$userId] = [];
			return [];
		}

		$circlesManager->startSession($circlesManager->getLocalFederatedUser($userId));
		try {
			$result = [];
			foreach ($circlesManager->probeCircles() as $circle) {
				$result[$circle->getSingleId()] = $circle;
			}
			$this->circlesByUser[$userId] = $result;
			return $result;
		} catch (\Exception $e) {
			$this->logger->warning('', ['exception' => $e]);
		} finally {
			$circlesManager->stopSession();
		}

		$this->circlesByUser[$userId] = [];
		return [];
	}

	public function getCirclesManager(): ?CirclesManager {
		try {
			return Server::get(CirclesManager::class);
		} catch (ContainerExceptionInterface|AutoloadNotAllowedException) {
			return null;
		}
	}

	#[\Override]
	public function userInMappings(IUser $user, array $mappings): bool {
		$userGroupIds = null;
		$circleMappings = [];
		foreach ($mappings as $mapping) {
			if ($mapping->getType() === 'user' && $mapping->getId() === $user->getUID()) {
				return true;
			}

			if ($mapping->getType() === 'group') {
				// Only fetch the user's groups once we know we actually need them.
				$userGroupIds ??= array_flip($this->groupManager->getUserGroupIds($user));
				if (isset($userGroupIds[$mapping->getId()])) {
					return true;
				}
			}

			if ($mapping->getType() === 'circle') {
				$circleMappings[] = $mapping->getId();
			}
		}

		if ($circleMappings === []) {
			return false;
		}

		// This is expensive do it at the end if we didn't match any group or user mapping first
		$circleIds = array_keys($this->getUserCircles($user->getUID()));
		return array_any($circleMappings, fn (string $circleId): bool => in_array($circleId, $circleIds));
	}

	#[\Override]
	public function resetCache(): void {
		$this->mappingByKey = new CappedMemoryCache();
		$this->mappingsByUser = new CappedMemoryCache();
		$this->circlesByUser = new CappedMemoryCache();
	}
}
