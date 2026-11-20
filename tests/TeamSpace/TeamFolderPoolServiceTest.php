<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\TeamSpace;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\TeamSpace\TeamFolderPoolService;
use OCA\GroupFolders\TeamSpace\TeamSpaceService;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Server;
use OCP\Teams\ITeamManager;
use OCP\Teams\Team;
use OCP\Teams\TeamFolderPoolQuotaException;
use PHPUnit\Framework\TestCase;

class TeamFolderPoolServiceTest extends TestCase {
	private IDBConnection $connection;
	private TeamFolderPoolService $service;
	private string $groupId;
	private string $teamId;
	/** @var list<int> */
	private array $folderIds = [];
	/** @var list<string> */
	private array $groupIds = [];
	/** @var list<string> */
	private array $teamIds = [];
	/** @var list<int> */
	private array $cacheIds = [];

	protected function setUp(): void {
		$this->connection = Server::get(IDBConnection::class);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturn($this->createMock(IGroup::class));
		$teams = $this->createMock(ITeamManager::class);
		$teams->method('getTeam')->willReturn(new Team('source', 'Source', null));
		$this->service = new TeamFolderPoolService($this->connection, $groups, $teams);
		$this->groupId = 'pool-test-' . bin2hex(random_bytes(8));
		$this->teamId = 'team-test-' . bin2hex(random_bytes(8));
		$this->groupIds[] = $this->groupId;
		$this->teamIds[] = $this->teamId;
		Server::get(IGroupManager::class)->createGroup($this->groupId);
	}

	protected function tearDown(): void {
		foreach ($this->cacheIds as $cacheId) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('filecache')->where($query->expr()->eq('fileid', $query->createNamedParameter($cacheId)))->executeStatement();
		}
		foreach ($this->folderIds as $folderId) {
			foreach (['group_folders_groups', 'group_folders'] as $table) {
				$query = $this->connection->getQueryBuilder();
				$query->delete($table)->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)))->executeStatement();
			}
		}
		foreach ($this->teamIds as $teamId) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('group_folders_team_pool')->where($query->expr()->eq('team_id', $query->createNamedParameter($teamId)))->executeStatement();
			$query = $this->connection->getQueryBuilder();
			$query->delete('group_folders_pool')->where($query->expr()->eq('id', $query->createNamedParameter('team:' . $teamId)))->executeStatement();
		}
		foreach ($this->groupIds as $groupId) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('group_folders_pool')->where($query->expr()->eq('id', $query->createNamedParameter('group:' . $groupId)))->executeStatement();
			Server::get(IGroupManager::class)->get($groupId)?->delete();
		}
	}

	public function testAllocationUsesFolderQuotaNotFileUsage(): void {
		$this->service->setLimit($this->groupId, 100);
		$this->createFolder(60);
		$this->service->assign($this->teamId, $this->groupId);

		$pool = $this->service->setLimit($this->groupId, 100);
		$this->assertSame(60, $pool->allocated);
		$this->assertFalse($pool->hasUnlimitedFolders);
	}

	public function testTeamQuotaUpdateWithoutFileCachePreservesPoolEnforcement(): void {
		$this->service->setLimit($this->groupId, 100);
		$folderId = $this->createFolder(60);
		$this->service->assign($this->teamId, $this->groupId);
		$teamSpaces = Server::get(TeamSpaceService::class);

		$folder = $teamSpaces->updateTeamSpaceQuota($this->teamId, 80);

		$this->assertSame($folderId, $folder->getId());
		$this->assertSame($this->teamId, $folder->getMountPoint());
		$this->assertSame(80, $folder->getQuota());
		$this->assertSame(80, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
		try {
			$teamSpaces->updateTeamSpaceQuota($this->teamId, 101);
			$this->fail('Quota increase beyond the pool budget must fail');
		} catch (TeamFolderPoolQuotaException) {
			$this->assertSame(80, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
		}
	}

	public function testDeletingPoolMovesAllTeamsToDefault(): void {
		$this->service->setLimit($this->groupId, 100);
		$this->createFolder(20);
		$this->service->assign($this->teamId, $this->groupId);
		$firstTeamId = $this->teamId;
		$this->teamId = 'team-test-' . bin2hex(random_bytes(8));
		$this->teamIds[] = $this->teamId;
		$this->createFolder(30);
		$this->service->assign($this->teamId, $this->groupId);

		$this->service->deletePool($this->groupId);

		$this->assertSame([], $this->service->getPools(['group:' . $this->groupId]));
		$assignments = $this->service->getAssignments([$firstTeamId, $this->teamId]);
		$this->assertSame('default', $assignments[$firstTeamId]);
		$this->assertSame('default', $assignments[$this->teamId]);
		$this->assertSame(30, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
	}

	public function testDeletingPoolMovesTeamsToDefaultEvenWhenItBecomesOverbooked(): void {
		$default = $this->service->getPools(['default'], false)[0];
		if ($default->limit === null) {
			$this->markTestSkipped('Requires a finite default pool');
		}
		$this->service->setLimit($this->groupId, null);
		$folderId = $this->createFolder($default->limit + 1);
		$this->service->assign($this->teamId, $this->groupId);

		$this->service->deletePool($this->groupId);

		$this->assertSame([], $this->service->getPools(['group:' . $this->groupId]));
		$this->assertSame('default', $this->service->getAssignments([$this->teamId])[$this->teamId]);
		$this->changeQuota($folderId, $default->limit);
		$this->assertSame($default->limit, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
		try {
			$this->changeQuota($folderId, $default->limit + 1);
			$this->fail('Increasing a folder in an overbooked pool must fail');
		} catch (TeamFolderPoolQuotaException) {
			$this->assertSame($default->limit, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
		}
	}

	private function changeQuota(int $folderId, int $quota): void {
		$this->service->atomic(function () use ($folderId, $quota): void {
			$this->service->trackFolderChange($folderId, $quota);
			$query = $this->connection->getQueryBuilder();
			$query->update('group_folders')->set('quota', $query->createNamedParameter($quota))
				->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)))->executeStatement();
		});
	}

	public function testDefaultPoolCannotBeDeleted(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->deletePool(null);
	}

	public function testDeletingTeamPoolPreservesOtherPools(): void {
		$this->service->setLimit(null, 100, $this->teamId);
		$this->service->setLimit($this->groupId, 100);
		$this->createFolder(20);
		$this->service->assign($this->teamId, null, false, $this->teamId);

		$this->service->deletePool(null, $this->teamId);

		$this->assertSame([], $this->service->getPools(['team:' . $this->teamId]));
		$this->assertSame('default', $this->service->getAssignments([$this->teamId])[$this->teamId]);
		$this->assertCount(1, $this->service->getPools(['group:' . $this->groupId]));
	}

	public function testRejectedMovePreservesOriginalAssignment(): void {
		$this->service->setLimit($this->groupId, 100);
		$this->createFolder(60);
		$this->service->assign($this->teamId, $this->groupId);
		$destination = $this->groupId . '-other';
		$this->groupIds[] = $destination;
		$this->service->setLimit($destination, 50);

		try {
			$this->service->assign($this->teamId, $destination);
			$this->fail('Oversized allocation must fail');
		} catch (TeamFolderPoolQuotaException $exception) {
			$this->assertSame(50, $exception->pool->limit);
		}
		$this->assertSame('group:' . $this->groupId, $this->service->getAssignments([$this->teamId])[$this->teamId]);
	}

	public function testQuotaChangeRollsBackWhenPoolIsExceeded(): void {
		$this->service->setLimit($this->groupId, 100);
		$folderId = $this->createFolder(60);
		$this->service->assign($this->teamId, $this->groupId);

		try {
			$this->service->atomic(function () use ($folderId): void {
				$this->service->trackFolderChange($folderId, 101);
				$query = $this->connection->getQueryBuilder();
				$query->update('group_folders')->set('quota', $query->createNamedParameter(101))
					->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)))->executeStatement();
			});
			$this->fail('Quota increase must fail');
		} catch (TeamFolderPoolQuotaException) {
			$this->assertSame(60, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
		}
	}

	public function testFinitePoolRejectsUnlimitedFolder(): void {
		$this->service->setLimit($this->groupId, 100);
		$this->createFolder(-3);
		$this->expectException(TeamFolderPoolQuotaException::class);

		$this->service->assign($this->teamId, $this->groupId);
	}

	public function testLimitCannotBeReducedBelowAllocatedBudget(): void {
		$this->service->setLimit($this->groupId, 100);
		$this->createFolder(60);
		$this->service->assign($this->teamId, $this->groupId);
		try {
			$this->service->setLimit($this->groupId, 59);
			$this->fail('Limit reduction must fail');
		} catch (TeamFolderPoolQuotaException) {
			$this->assertSame(100, $this->service->setLimit($this->groupId, 100)->limit);
		}
	}

	public function testInitialAssignmentDoesNotMoveExistingTeam(): void {
		$this->service->setLimit($this->groupId, 100);
		$this->service->assign($this->teamId, $this->groupId);
		$this->service->assign($this->teamId, null, true);

		$this->assertSame('group:' . $this->groupId, $this->service->getAssignments([$this->teamId])[$this->teamId]);
	}

	public function testUnlimitedPoolReportsUnlimitedFolder(): void {
		$this->service->setLimit($this->groupId, null);
		$this->createFolder(-3);
		$this->service->assign($this->teamId, $this->groupId);

		$this->assertTrue($this->service->setLimit($this->groupId, null)->hasUnlimitedFolders);
	}

	public function testConcurrentQuotaIncreasesCannotOverbookPool(): void {
		if (!function_exists('proc_open')) {
			$this->markTestSkipped('Concurrent integration check requires proc_open');
		}
		$this->service->setLimit($this->groupId, 100);
		$firstFolder = $this->createFolder(20);
		$this->service->assign($this->teamId, $this->groupId);
		$this->teamId = 'team-test-' . bin2hex(random_bytes(8));
		$this->teamIds[] = $this->teamId;
		$secondFolder = $this->createFolder(20);
		$this->service->assign($this->teamId, $this->groupId);
		$processes = [];
		$pipesByProcess = [];
		foreach ([$firstFolder, $secondFolder] as $folderId) {
			$code = 'require ' . var_export(__DIR__ . '/../bootstrap.php', true) . ';'
				. 'fgets(STDIN);'
				. 'try { \OCP\Server::get(\OCA\GroupFolders\Folder\FolderManager::class)->setFolderQuota(' . $folderId . ', 60); echo "ok"; }'
				. 'catch (\OCP\Teams\TeamFolderPoolQuotaException $exception) { echo "rejected"; }';
			$process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
			$this->assertIsResource($process);
			$processes[] = $process;
			$pipesByProcess[] = $pipes;
		}
		foreach ($pipesByProcess as $pipes) {
			fwrite($pipes[0], "go\n");
			fclose($pipes[0]);
		}
		$outputs = [];
		foreach ($processes as $index => $process) {
			$pipes = $pipesByProcess[$index];
			$outputs[] = stream_get_contents($pipes[1]);
			$error = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$this->assertSame(0, proc_close($process), $error);
		}
		sort($outputs);
		$this->assertSame(['ok', 'rejected'], $outputs);
		$this->assertSame(80, $this->service->setLimit($this->groupId, 100)->allocated);
	}

	public function testCentralSetterRejectsUnlimitedQuotaInFinitePool(): void {
		$this->service->setLimit($this->groupId, 100);
		$folderId = $this->createFolder(60);
		$this->service->assign($this->teamId, $this->groupId);
		try {
			Server::get(FolderManager::class)->setFolderQuota($folderId, -3);
			$this->fail('Unlimited quota must not fit in a finite pool');
		} catch (TeamFolderPoolQuotaException) {
			$this->assertSame(60, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
		}
	}

	public function testChangesRejectForeignTransactionSnapshot(): void {
		$this->connection->beginTransaction();
		try {
			$this->expectException(\LogicException::class);
			$this->service->setLimit($this->groupId, 100);
		} finally {
			$this->connection->rollBack();
		}
	}

	public function testOrdinaryFolderQuotaCanChangeInForeignTransaction(): void {
		$folderId = $this->createFolder(20, FolderDefinition::MANAGED_BY_GROUPFOLDERS);
		$this->connection->beginTransaction();
		try {
			Server::get(FolderManager::class)->setFolderQuota($folderId, 40);
			Server::get(FolderManager::class)->setFolderQuota($folderId, 40);
			$query = $this->connection->getQueryBuilder();
			$query->select('quota')->from('group_folders')
				->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)));
			$result = $query->executeQuery();
			/** @var int|string $quota */
			$quota = $result->fetchOne();
			$result->closeCursor();
			$this->assertSame(40, (int)$quota);
		} finally {
			$this->connection->rollBack();
		}
	}

	public function testMarkingExistingFolderCannotExceedPool(): void {
		$this->service->setLimit($this->groupId, 50);
		$folderId = $this->createFolder(60, FolderDefinition::MANAGED_BY_GROUPFOLDERS);
		$this->service->assign($this->teamId, $this->groupId);
		try {
			Server::get(FolderManager::class)->markAsTeamFolder($folderId);
			$this->fail('Linking an oversized folder must fail');
		} catch (TeamFolderPoolQuotaException) {
			$this->assertSame([], $this->service->getFolders([$this->teamId]));
			$this->assertSame(0, $this->service->setLimit($this->groupId, 50)->allocated);
		}
	}

	public function testMarkingRejectsGroupIdZero(): void {
		$folderId = $this->createFolder(20, FolderDefinition::MANAGED_BY_GROUPFOLDERS);
		$query = $this->connection->getQueryBuilder();
		$query->update('group_folders_groups')
			->set('group_id', $query->createNamedParameter('0'))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)))->executeStatement();
		$this->expectException(\InvalidArgumentException::class);
		Server::get(FolderManager::class)->markAsTeamFolder($folderId);
	}

	public function testTeamPoolAllocationAndRejectedIncrease(): void {
		$this->service->setLimit(null, 100, $this->teamId);
		$folderId = $this->createFolder(60);
		$this->service->assign($this->teamId, null, false, $this->teamId);
		$pool = $this->service->setLimit(null, 100, $this->teamId);
		$this->assertSame($this->teamId, $pool->teamId);
		$this->assertSame(60, $pool->allocated);
		$this->assertSame(100, $this->service->getLimits(true)[$this->teamId] ?? null);
		$this->assertArrayNotHasKey($this->teamId, $this->service->getLimits());
		$this->assertSame('team:' . $this->teamId, $this->service->getAssignments([$this->teamId])[$this->teamId]);
		try {
			Server::get(FolderManager::class)->setFolderQuota($folderId, 101);
			$this->fail('Team pool quota increase must fail');
		} catch (TeamFolderPoolQuotaException) {
			$this->assertSame(60, $this->service->getFolders([$this->teamId])[$this->teamId]->getQuota());
		}
	}

	public function testMissingFinancingTeamCannotHavePool(): void {
		$service = new TeamFolderPoolService($this->connection, $this->createMock(IGroupManager::class), $this->createMock(ITeamManager::class));
		$this->expectException(\InvalidArgumentException::class);
		$service->setLimit(null, 100, 'missing-team');
	}

	public function testUsageReadsFolderRootSizeNotAssignedQuota(): void {
		$this->service->setLimit(null, 100, $this->teamId);
		$folderId = $this->createFolder(60);
		$this->service->assign($this->teamId, null, false, $this->teamId);
		$this->assertNull($this->service->setLimit(null, 100, $this->teamId)->used);
		$query = $this->connection->getQueryBuilder();
		$query->insert('filecache')->values([
			'storage' => $query->createNamedParameter(0),
			'path' => $query->createNamedParameter($this->teamId),
			'path_hash' => $query->createNamedParameter(md5($this->teamId)),
			'name' => $query->createNamedParameter($this->teamId),
			'size' => $query->createNamedParameter(17),
		]);
		$query->executeStatement();
		$cacheId = $query->getLastInsertId();
		$this->cacheIds[] = $cacheId;
		$query = $this->connection->getQueryBuilder();
		$query->update('group_folders')->set('root_id', $query->createNamedParameter($cacheId))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId)))->executeStatement();
		$pool = $this->service->setLimit(null, 100, $this->teamId);
		$this->assertSame(60, $pool->allocated);
		$this->assertSame(17, $pool->used);
		$this->assertSame([$this->teamId => 17], $this->service->getFolderUsage([$this->teamId]));
	}

	private function createFolder(int $quota, string $managedBy = FolderDefinition::MANAGED_BY_TEAMS): int {
		$query = $this->connection->getQueryBuilder();
		$query->insert('group_folders')->values([
			'mount_point' => $query->createNamedParameter($this->teamId),
			'quota' => $query->createNamedParameter($quota),
			'managed_by' => $query->createNamedParameter($managedBy),
		]);
		$query->executeStatement();
		$folderId = $query->getLastInsertId();
		$this->folderIds[] = $folderId;
		$query = $this->connection->getQueryBuilder();
		$query->insert('group_folders_groups')->values([
			'folder_id' => $query->createNamedParameter($folderId),
			'circle_id' => $query->createNamedParameter($this->teamId),
			'group_id' => $query->createNamedParameter(''),
			'permissions' => $query->createNamedParameter(31),
		]);
		$query->executeStatement();
		return $folderId;
	}
}
