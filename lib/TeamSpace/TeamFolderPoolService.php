<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\TeamSpace;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Teams\ITeamManager;
use OCP\Teams\TeamFolder;
use OCP\Teams\TeamFolderPoolQuotaException;
use OCP\Teams\TeamFolderStoragePool;

class TeamFolderPoolService {
	private bool $mutating = false;
	/** @var array<string, true> */
	private array $affectedPools = [];
	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IGroupManager $groupManager,
		private readonly ITeamManager $teamManager,
	) {
	}

	/**
	 * @param list<string>|null $poolIds
	 * @return list<TeamFolderStoragePool>
	 */
	public function getPools(?array $poolIds = null, bool $includeUsage = true): array {
		if ($poolIds === []) {
			return [];
		}
		$query = $this->connection->getQueryBuilder();
		$query->select('p.id', 'p.group_id', 'p.team_id', 'p.limit_bytes')
			->addSelect($query->createFunction('COALESCE(SUM(CASE WHEN f.quota > 0 THEN f.quota ELSE 0 END), 0) AS allocated'))
			->addSelect($query->createFunction('COALESCE(SUM(CASE WHEN f.quota < 0 THEN 1 ELSE 0 END), 0) AS unlimited_count'))
			->from('group_folders_pool', 'p')
			->leftJoin('p', 'group_folders_team_pool', 't', $query->expr()->eq('t.pool_id', 'p.id'))
			->leftJoin('t', 'group_folders_groups', 'g', $query->expr()->eq('g.circle_id', 't.team_id'))
			->leftJoin('g', 'group_folders', 'f', $query->expr()->andX(
				$query->expr()->eq('f.folder_id', 'g.folder_id'),
				$query->expr()->eq('f.managed_by', $query->createNamedParameter(FolderDefinition::MANAGED_BY_TEAMS)),
			))
			->groupBy('p.id', 'p.group_id', 'p.team_id', 'p.limit_bytes')
			->orderBy('p.id');
		if ($includeUsage) {
			$query->leftJoin('f', 'filecache', 'c', $query->expr()->eq('c.fileid', 'f.root_id'))
				->addSelect($query->createFunction('COALESCE(SUM(CASE WHEN c.size >= 0 THEN c.size ELSE 0 END), 0) AS used'))
				->addSelect($query->createFunction('COALESCE(SUM(CASE WHEN f.folder_id IS NOT NULL AND (c.fileid IS NULL OR c.size < 0) THEN 1 ELSE 0 END), 0) AS unknown_count'));
		}
		if ($poolIds !== null) {
			$query->where($query->expr()->in('p.id', $query->createNamedParameter($poolIds, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		$result = $query->executeQuery();
		$pools = [];
		while ($row = $result->fetchAssociative()) {
			/** @var array{id: string, group_id: ?string, team_id: ?string, limit_bytes: int|string|null, allocated: int|string, unlimited_count: int|string, used?: int|string, unknown_count?: int|string} $row */
			$pools[] = new TeamFolderStoragePool(
				$row['id'],
				$row['group_id'],
				$row['limit_bytes'] === null ? null : (int)$row['limit_bytes'],
				(int)$row['allocated'],
				(int)$row['unlimited_count'] > 0,
				$row['team_id'],
				$includeUsage && (int)($row['unknown_count'] ?? 0) === 0 ? (int)($row['used'] ?? 0) : null,
			);
		}
		$result->closeCursor();
		return $pools;
	}

	/** @return array<string, int|null> */
	public function getLimits(bool $teams = false): array {
		$column = $teams ? 'team_id' : 'group_id';
		$query = $this->connection->getQueryBuilder();
		$query->select($column, 'limit_bytes')->from('group_folders_pool')
			->where($query->expr()->isNotNull($column));
		$result = $query->executeQuery();
		$limits = [];
		while ($row = $result->fetchAssociative()) {
			/** @var array<string, int|string|null> $row */
			$limits[(string)$row[$column]] = $row['limit_bytes'] === null ? null : (int)$row['limit_bytes'];
		}
		$result->closeCursor();
		return $limits;
	}

	public function setLimit(?string $groupId, ?int $limit, ?string $sourceTeamId = null): TeamFolderStoragePool {
		if ($limit !== null && ($limit < 0 || $limit > 9007199254740991)) {
			throw new \InvalidArgumentException('Storage pool limit must be a safe non-negative integer');
		}
		$poolId = $this->poolId($groupId, $sourceTeamId);
		$this->atomic(function () use ($groupId, $poolId, $limit, $sourceTeamId): void {
			$this->affectedPools[$poolId] = true;
			$this->ensurePool($groupId, $sourceTeamId);
			$query = $this->connection->getQueryBuilder();
			$query->update('group_folders_pool')
				->set('limit_bytes', $query->createNamedParameter($limit, $limit === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT))
				->where($query->expr()->eq('id', $query->createNamedParameter($poolId)));
			$query->executeStatement();
		});
		foreach ($this->getPools([$poolId]) as $pool) {
			if ($pool->id === $poolId) {
				return $pool;
			}
		}
		throw new \RuntimeException('Storage pool not found');
	}

	public function deletePool(?string $groupId, ?string $sourceTeamId = null): void {
		if (($groupId === null && $sourceTeamId === null) || ($groupId !== null && $sourceTeamId !== null)) {
			throw new \InvalidArgumentException('Select a group or team pool to delete');
		}
		$poolId = $this->poolId($groupId, $sourceTeamId);
		// Released teams always move to the default pool, even if it becomes overbooked.
		$this->atomic(function () use ($poolId): void {
			$query = $this->connection->getQueryBuilder();
			$query->update('group_folders_team_pool')
				->set('pool_id', $query->createNamedParameter('default'))
				->where($query->expr()->eq('pool_id', $query->createNamedParameter($poolId)));
			$query->executeStatement();
			$query = $this->connection->getQueryBuilder();
			$query->delete('group_folders_pool')
				->where($query->expr()->eq('id', $query->createNamedParameter($poolId)));
			$query->executeStatement();
		});
	}

	public function assign(string $teamId, ?string $groupId, bool $onlyIfUnassigned = false, ?string $sourceTeamId = null): void {
		if ($teamId === '' || strlen($teamId) > 64) {
			throw new \InvalidArgumentException('Invalid team id');
		}
		$this->atomic(function () use ($teamId, $groupId, $onlyIfUnassigned, $sourceTeamId): void {
			$assignments = $this->getAssignments([$teamId]);
			if ($onlyIfUnassigned && isset($assignments[$teamId])) {
				return;
			}
			$this->ensurePool($groupId, $sourceTeamId);
			$this->affectedPools[$this->poolId($groupId, $sourceTeamId)] = true;
			$query = $this->connection->getQueryBuilder();
			if (isset($assignments[$teamId])) {
				$query->update('group_folders_team_pool')
					->set('pool_id', $query->createNamedParameter($this->poolId($groupId, $sourceTeamId)))
					->where($query->expr()->eq('team_id', $query->createNamedParameter($teamId)));
			} else {
				$query->insert('group_folders_team_pool')->values([
					'team_id' => $query->createNamedParameter($teamId),
					'pool_id' => $query->createNamedParameter($this->poolId($groupId, $sourceTeamId)),
				]);
			}
			$query->executeStatement();
		});
	}

	/**
	 * @param list<string> $teamIds
	 * @return array<string, string>
	 */
	public function getAssignments(array $teamIds): array {
		$assignments = [];
		foreach (array_chunk($teamIds, 500) as $chunk) {
			$query = $this->connection->getQueryBuilder();
			$query->select('team_id', 'pool_id')->from('group_folders_team_pool')
				->where($query->expr()->in('team_id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				/** @var array{team_id: string, pool_id: string} $row */
				$assignments[$row['team_id']] = $row['pool_id'];
			}
			$result->closeCursor();
		}
		return $assignments;
	}

	/**
	 * @param list<string> $teamIds
	 * @return array<string, TeamFolder>
	 */
	public function getFolders(array $teamIds): array {
		$folders = [];
		foreach (array_chunk($teamIds, 500) as $chunk) {
			$query = $this->connection->getQueryBuilder();
			$query->select('g.circle_id', 'f.folder_id', 'f.mount_point', 'f.quota')
				->from('group_folders', 'f')
				->innerJoin('f', 'group_folders_groups', 'g', $query->expr()->eq('g.folder_id', 'f.folder_id'))
				->where($query->expr()->eq('f.managed_by', $query->createNamedParameter(FolderDefinition::MANAGED_BY_TEAMS)))
				->andWhere($query->expr()->in('g.circle_id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				/** @var array{circle_id: string, folder_id: int|string, mount_point: string, quota: int|string} $row */
				$folders[$row['circle_id']] = new TeamFolder((int)$row['folder_id'], $row['mount_point'], max(0, (int)$row['quota']));
			}
			$result->closeCursor();
		}
		return $folders;
	}

	/**
	 * @param list<string> $teamIds
	 * @return array<string, int|null>
	 */
	public function getFolderUsage(array $teamIds): array {
		$usage = [];
		foreach (array_chunk($teamIds, 500) as $chunk) {
			$query = $this->connection->getQueryBuilder();
			$query->select('g.circle_id', 'c.size')->from('group_folders', 'f')
				->innerJoin('f', 'group_folders_groups', 'g', $query->expr()->eq('g.folder_id', 'f.folder_id'))
				->leftJoin('f', 'filecache', 'c', $query->expr()->eq('c.fileid', 'f.root_id'))
				->where($query->expr()->eq('f.managed_by', $query->createNamedParameter(FolderDefinition::MANAGED_BY_TEAMS)))
				->andWhere($query->expr()->in('g.circle_id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
			$result = $query->executeQuery();
			while ($row = $result->fetchAssociative()) {
				/** @var array{circle_id: string, size: int|string|null} $row */
				$usage[$row['circle_id']] = $row['size'] === null || (int)$row['size'] < 0 ? null : (int)$row['size'];
			}
			$result->closeCursor();
		}
		return $usage;
	}

	public function trackFolderChange(int $folderId, ?int $newQuota = null): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('p.id', 'p.group_id', 'f.quota')
			->from('group_folders', 'f')
			->innerJoin('f', 'group_folders_groups', 'g', $query->expr()->eq('g.folder_id', 'f.folder_id'))
			->innerJoin('g', 'group_folders_team_pool', 't', $query->expr()->eq('t.team_id', 'g.circle_id'))
			->innerJoin('t', 'group_folders_pool', 'p', $query->expr()->eq('p.id', 't.pool_id'))
			->where($query->expr()->eq('f.folder_id', $query->createNamedParameter($folderId)))
			->andWhere($query->expr()->eq('f.managed_by', $query->createNamedParameter(FolderDefinition::MANAGED_BY_TEAMS)));
		$result = $query->executeQuery();
		while ($row = $result->fetchAssociative()) {
			/** @var array{id: string, group_id: ?string, quota: int|string} $row */
			$oldQuota = (int)$row['quota'];
			$isIncrease = $newQuota === null || ($oldQuota >= 0 && ($newQuota < 0 || $newQuota > $oldQuota));
			if (!$isIncrease) {
				continue;
			}
			$this->affectedPools[$row['id']] = true;
			if ($row['group_id'] !== null && $this->groupManager->get($row['group_id']) === null) {
				$result->closeCursor();
				throw new \InvalidArgumentException('The financing group no longer exists; move the team to another storage pool');
			}
		}
		$result->closeCursor();
	}

	/**
	 * @template T
	 * @param callable(): T $change
	 * @return T
	 */
	public function atomic(callable $change): mixed {
		if ($this->mutating) {
			return $change();
		}
		if ($this->connection->inTransaction()) {
			throw new \LogicException('Storage pool changes require a fresh database transaction');
		}
		$this->connection->beginTransaction();
		$this->mutating = true;
		$this->affectedPools = [];
		try {
			$query = $this->connection->getQueryBuilder();
			$query->update('group_folders_pool')
				->set('revision', $query->createFunction('revision + 1'))
				->where($query->expr()->eq('id', $query->createNamedParameter('default')));
			if ($query->executeStatement() !== 1) {
				throw new \RuntimeException('Storage pool schema is not initialized');
			}
			$result = $change();
			foreach ($this->getPools(array_keys($this->affectedPools), false) as $pool) {
				if ($pool->limit !== null && ($pool->hasUnlimitedFolders || $pool->allocated > $pool->limit)) {
					throw new TeamFolderPoolQuotaException($pool);
				}
			}
			$this->connection->commit();
			return $result;
		} catch (\Throwable $exception) {
			$this->connection->rollBack();
			throw $exception;
		} finally {
			$this->mutating = false;
			$this->affectedPools = [];
		}
	}

	private function ensurePool(?string $groupId, ?string $sourceTeamId = null): void {
		if ($sourceTeamId !== null && ($groupId !== null || $sourceTeamId === '' || strlen($sourceTeamId) > 64 || $this->teamManager->getTeam($sourceTeamId) === null)) {
			throw new \InvalidArgumentException('Invalid financing team');
		}
		if ($groupId !== null && ($groupId === '' || strlen($groupId) > 255 || $this->groupManager->get($groupId) === null)) {
			throw new \InvalidArgumentException('Storage pool group does not exist');
		}
		$query = $this->connection->getQueryBuilder();
		$query->select('id')->from('group_folders_pool')
			->where($query->expr()->eq('id', $query->createNamedParameter($this->poolId($groupId, $sourceTeamId))));
		$result = $query->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		if (!$exists) {
			$query = $this->connection->getQueryBuilder();
			$query->insert('group_folders_pool')->values([
				'id' => $query->createNamedParameter($this->poolId($groupId, $sourceTeamId)),
				'group_id' => $query->createNamedParameter($groupId, $groupId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
				'team_id' => $query->createNamedParameter($sourceTeamId, $sourceTeamId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			]);
			$query->executeStatement();
		}
	}

	private function poolId(?string $groupId, ?string $sourceTeamId = null): string {
		if ($sourceTeamId !== null) {
			return 'team:' . $sourceTeamId;
		}
		return $groupId === null ? 'default' : 'group:' . $groupId;
	}
}
