<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCA\GroupFolders\Mount\FolderStorageManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\ICache;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\InvalidPathException;
use OCP\IDBConnection;
use OCP\Log\Audit\CriticalActionPerformedEvent;

/**
 * Stores and resolves quotas assigned to direct child folders of Team folders.
 */
class SubfolderQuotaManager {
	/** @var array<int, array<int, int>> */
	private array $quotaCache = [];

	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IMimeTypeLoader $mimeTypeLoader,
		private readonly FolderStorageManager $folderStorageManager,
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * @return list<SubfolderQuota>
	 */
	public function getSubfolderQuotas(FolderDefinition $folder): array {
		$query = $this->connection->getQueryBuilder();
		$query->select('f.fileid', 'f.name', 'f.size', 'q.quota')
			->from('filecache', 'f')
			->leftJoin(
				'f',
				'gf_subfolder_quota',
				'q',
				$query->expr()->andX(
					$query->expr()->eq('q.folder_id', $query->createNamedParameter($folder->id, IQueryBuilder::PARAM_INT)),
					$query->expr()->eq('q.file_id', 'f.fileid'),
				),
			)
			->where($query->expr()->eq('f.parent', $query->createNamedParameter($folder->rootId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('f.storage', $query->createNamedParameter($folder->storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('f.mimetype', $query->createNamedParameter($this->getDirectoryMimeTypeId(), IQueryBuilder::PARAM_INT)))
			->orderBy('f.name', 'ASC');

		/** @var list<array{fileid: int|string, name: string, size: int|string, quota: int|string|null}> $rows */
		$rows = $query->executeQuery()->fetchAll();

		return array_map($this->rowToSubfolderQuota(...), $rows);
	}

	/**
	 * Resolve the configured quota that applies to a storage path.
	 */
	public function getQuotaForPath(FolderDefinition $folder, ICache $cache, string $path): ?SubfolderQuota {
		$name = $this->getDirectChildName($path);
		if ($name === null) {
			return null;
		}

		$entry = $cache->get($name);
		if ($entry === false || $entry->getMimeType() !== FileInfo::MIMETYPE_FOLDER) {
			return null;
		}

		$quota = $this->getQuotaMap($folder->id)[$entry->getId()] ?? null;
		if ($quota === null) {
			return null;
		}

		return new SubfolderQuota(
			$entry->getId(),
			$entry->getName(),
			$entry->getSize(),
			$quota,
		);
	}

	/**
	 * @throws \InvalidArgumentException when the file is not a direct child folder
	 */
	public function setSubfolderQuota(FolderDefinition $folder, int $fileId, int $quota): SubfolderQuota {
		$this->validateQuota($quota);
		$subfolder = $this->getDirectSubfolder($folder, $fileId);
		if ($subfolder === null) {
			throw new \InvalidArgumentException('The quota can only be assigned to a direct subfolder of this Team folder');
		}

		if ($quota === FileInfo::SPACE_UNLIMITED) {
			$this->removeSubfolderQuota($folder->id, $fileId);
			return new SubfolderQuota($subfolder->fileId, $subfolder->name, $subfolder->size, null);
		}

		$query = $this->connection->getQueryBuilder();
		$query->update('gf_subfolder_quota')
			->set('quota', $query->createNamedParameter($quota, IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folder->id, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		if ($query->executeStatement() === 0) {
			$query = $this->connection->getQueryBuilder();
			$query->insert('gf_subfolder_quota')
				->values([
					'folder_id' => $query->createNamedParameter($folder->id, IQueryBuilder::PARAM_INT),
					'file_id' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
					'quota' => $query->createNamedParameter($quota, IQueryBuilder::PARAM_INT),
				]);
			$query->executeStatement();
		}

		$this->invalidateQuotaCache($folder->id);
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The quota for subfolder with id %d in groupfolder with id %d was set to %d bytes', [$fileId, $folder->id, $quota]));

		return new SubfolderQuota($subfolder->fileId, $subfolder->name, $subfolder->size, $quota);
	}

	/**
	 * Create a direct child folder and optionally assign a quota to it.
	 *
	 * @throws \InvalidArgumentException when the name or quota is invalid
	 * @throws \RuntimeException when the folder cannot be created or scanned
	 */
	public function createSubfolder(FolderDefinition $folder, string $name, int $quota): SubfolderQuota {
		$name = trim($name);
		$this->validateSubfolderName($name);
		$this->validateQuota($quota);

		$storage = $this->folderStorageManager->getBaseStorageForFolder(
			$folder->id,
			$folder->useSeparateStorage(),
			$folder,
		);
		try {
			$storage->verifyPath('', $name);
		} catch (InvalidPathException $exception) {
			throw new \InvalidArgumentException($exception->getMessage(), previous: $exception);
		}
		if ($storage->file_exists($name)) {
			throw new \InvalidArgumentException('A file or folder with this name already exists');
		}
		if (!$storage->mkdir($name)) {
			throw new \RuntimeException('Unable to create the subfolder');
		}

		$cache = $storage->getCache();
		$entry = $cache->get($name);
		if ($entry === false) {
			$storage->getScanner()->scan($name);
			$entry = $cache->get($name);
		}
		if ($entry === false) {
			throw new \RuntimeException('The created subfolder could not be found in the file cache');
		}

		return $this->setSubfolderQuota($folder, $entry->getId(), $quota);
	}

	public function removeSubfolderQuota(int $folderId, int $fileId): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('gf_subfolder_quota')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$this->invalidateQuotaCache($folderId);
	}

	public function hasSubfolderQuota(int $folderId, int $fileId): bool {
		return isset($this->getQuotaMap($folderId)[$fileId]);
	}

	/**
	 * Remove any quota that refers to a deleted file cache entry.
	 */
	public function removeSubfolderQuotaByFileId(int $fileId): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('folder_id')
			->from('gf_subfolder_quota')
			->where($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		/** @var list<int|string> $folderIds */
		$folderIds = $query->executeQuery()->fetchFirstColumn();

		$query = $this->connection->getQueryBuilder();
		$query->delete('gf_subfolder_quota')
			->where($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		foreach ($folderIds as $folderId) {
			$this->invalidateQuotaCache((int)$folderId);
		}
	}

	public function removeSubfolderQuotasForFolder(int $folderId): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('gf_subfolder_quota')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();

		$this->invalidateQuotaCache($folderId);
	}

	/**
	 * @return array<int, int>
	 */
	private function getQuotaMap(int $folderId): array {
		if (isset($this->quotaCache[$folderId])) {
			return $this->quotaCache[$folderId];
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('file_id', 'quota')
			->from('gf_subfolder_quota')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)));
		/** @var list<array{file_id: int|string, quota: int|string}> $rows */
		$rows = $query->executeQuery()->fetchAll();

		$this->quotaCache[$folderId] = [];
		foreach ($rows as $row) {
			$this->quotaCache[$folderId][(int)$row['file_id']] = (int)$row['quota'];
		}

		return $this->quotaCache[$folderId];
	}

	private function invalidateQuotaCache(int $folderId): void {
		unset($this->quotaCache[$folderId]);
	}

	private function getDirectoryMimeTypeId(): int {
		return $this->mimeTypeLoader->getId(FileInfo::MIMETYPE_FOLDER);
	}

	private function getDirectChildName(string $path): ?string {
		$path = trim($path, '/');
		if ($path === '' || $path === '.') {
			return null;
		}

		return explode('/', $path, 2)[0];
	}

	private function getDirectSubfolder(FolderDefinition $folder, int $fileId): ?SubfolderQuota {
		$query = $this->connection->getQueryBuilder();
		$query->select('f.fileid', 'f.name', 'f.size', 'q.quota')
			->from('filecache', 'f')
			->leftJoin(
				'f',
				'gf_subfolder_quota',
				'q',
				$query->expr()->andX(
					$query->expr()->eq('q.folder_id', $query->createNamedParameter($folder->id, IQueryBuilder::PARAM_INT)),
					$query->expr()->eq('q.file_id', 'f.fileid'),
				),
			)
			->where($query->expr()->eq('f.fileid', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('f.parent', $query->createNamedParameter($folder->rootId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('f.storage', $query->createNamedParameter($folder->storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('f.mimetype', $query->createNamedParameter($this->getDirectoryMimeTypeId(), IQueryBuilder::PARAM_INT)));

		/** @var array{fileid: int|string, name: string, size: int|string, quota: int|string|null}|false $row */
		$row = $query->executeQuery()->fetch();
		if ($row === false) {
			return null;
		}

		return $this->rowToSubfolderQuota($row);
	}

	/**
	 * @param array{fileid: int|string, name: string, size: int|string, quota: int|string|null} $row
	 */
	private function rowToSubfolderQuota(array $row): SubfolderQuota {
		return new SubfolderQuota(
			(int)$row['fileid'],
			$row['name'],
			(int)$row['size'],
			$row['quota'] === null ? null : (int)$row['quota'],
		);
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function validateQuota(int $quota): void {
		if ($quota < 0 && $quota !== FileInfo::SPACE_UNLIMITED) {
			throw new \InvalidArgumentException('The quota must be a non-negative number of bytes or unlimited');
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function validateSubfolderName(string $name): void {
		if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/')) {
			throw new \InvalidArgumentException('The subfolder name must name one direct child folder');
		}
	}
}
