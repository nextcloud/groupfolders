<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Folder;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderNameResolver;
use OCA\GroupFolders\Folder\IFolderNameResolver;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class FolderNameResolverTest extends TestCase {
	private IDBConnection $connection;
	private FolderNameResolver $resolver;
	/** @var list<int> */
	private array $createdIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->connection = Server::get(IDBConnection::class);
		$this->resolver = new FolderNameResolver($this->connection);
	}

	protected function tearDown(): void {
		if ($this->createdIds !== []) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('group_folders')
				->where($query->expr()->in('folder_id', $query->createNamedParameter($this->createdIds, IQueryBuilder::PARAM_INT_ARRAY)))
				->executeStatement();
		}
		parent::tearDown();
	}

	private function createFolder(string $mountPoint): int {
		$query = $this->connection->getQueryBuilder();
		$query->insert('group_folders')
			->values([
				'mount_point' => $query->createNamedParameter($mountPoint),
				// NOT NULL without a default since the folder-quota rework
				'quota' => $query->createNamedParameter(FolderManager::SPACE_DEFAULT, IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
		$id = $query->getLastInsertId();
		$this->createdIds[] = $id;
		return $id;
	}

	public function testIsTheRegisteredImplementationOfTheContract(): void {
		$this->assertInstanceOf(FolderNameResolver::class, Server::get(IFolderNameResolver::class));
	}

	public function testResolvesOneFolder(): void {
		$id = $this->createFolder('Finance');

		$this->assertSame('Finance', $this->resolver->getMountPoint($id));
	}

	public function testUnknownFolderIsNull(): void {
		$this->assertNull($this->resolver->getMountPoint(PHP_INT_MAX));
	}

	public function testResolvesManyFoldersInOneCallAndSkipsUnknownIds(): void {
		$finance = $this->createFolder('Finance');
		$legal = $this->createFolder('Legal');

		$mountPoints = $this->resolver->getMountPoints([$finance, PHP_INT_MAX, $legal, $finance]);

		ksort($mountPoints);
		$this->assertSame([$finance => 'Finance', $legal => 'Legal'], $mountPoints);
		$this->assertSame([], $this->resolver->getMountPoints([]));
	}
}
