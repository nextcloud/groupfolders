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

	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly LoggerInterface $logger,
	) {
		$this->mappingsByUser = new CappedMemoryCache();
		$this->mappingByKey = new CappedMemoryCache();
	}

	#[\Override]
	public function getMappingsForUser(IUser $user, bool $userAssignable = true): array {
		$cacheKey = $user->getUID() . '|' . (int)$userAssignable;
		$cached = $this->mappingsByUser->get($cacheKey);
		if ($cached !== null) {
			return $cached;
		}

		$groupMappings = array_values(array_map(fn (IGroup $group): UserMapping => new UserMapping('group', $group->getGID(), $group->getDisplayName()), $this->groupManager->getUserGroups($user)));
		$circleMappings = array_values(array_map(fn (Circle $circle): UserMapping => new UserMapping('circle', $circle->getSingleId(), $circle->getDisplayName()), $this->getUserCircles($user->getUID())));

		$mappings = array_merge([
			new UserMapping('user', $user->getUID(), $user->getDisplayName()),
		], $groupMappings, $circleMappings);

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

	#[\Override]
	public function userInMappings(IUser $user, array $mappings): bool {
		$userGroupIds = array_flip($this->groupManager->getUserGroupIds($user));

		$hasCircleMapping = false;
		foreach ($mappings as $mapping) {
			if ($mapping->getType() === 'user' && $mapping->getId() === $user->getUID()) {
				return true;
			}

			if ($mapping->getType() === 'group' && isset($userGroupIds[$mapping->getId()])) {
				return true;
			}

			if ($mapping->getType() === 'circle') {
				$hasCircleMapping = true;
			}
		}

		if (!$hasCircleMapping) {
			return false;
		}

		$mappingKeys = array_map(fn (IUserMapping $mapping): string => $mapping->getKey(), $mappings);

		$userMappings = $this->getMappingsForUser($user);
		foreach ($userMappings as $userMapping) {
			if (in_array($userMapping->getKey(), $mappingKeys, true)) {
				return true;
			}
		}
		return false;
	}

	#[\Override]
	public function resetCache(): void {
		$this->mappingByKey = new CappedMemoryCache();
		$this->mappingsByUser = new CappedMemoryCache();
	}
}
