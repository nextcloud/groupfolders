<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Log\Audit\CriticalActionPerformedEvent;

/**
 * Stores delegated administrators for direct children of Team folders.
 *
 * The mapping format deliberately matches Team folder ACL managers, so a
 * delegate may be a user, group, or Circle. Being a delegate grants no file
 * access by itself; normal Team folder and ACL permissions still apply.
 * Delegation is available only while advanced permissions are enabled. Its
 * mappings are retained while disabled so they can be reactivated later.
 */
class SubfolderManagerService {
	public function __construct(
		private readonly IDBConnection $connection,
		private readonly IUserMappingManager $userMappingManager,
		private readonly SubfolderQuotaManager $subfolderQuotaManager,
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * @return list<IUserMapping>
	 * @throws \InvalidArgumentException when the file is not a direct child
	 */
	public function getManagers(FolderDefinition $folder, int $fileId): array {
		if ($this->subfolderQuotaManager->getDirectSubfolder($folder, $fileId) === null) {
			throw new \InvalidArgumentException('The subfolder manager can only be assigned to a direct child of this Team folder');
		}
		if (!$this->hasManagerTable()) {
			return [];
		}

		return $this->getManagersForSubfolder($folder->id, $fileId);
	}

	public function canManageSubfolder(FolderDefinition $folder, int $fileId, IUser $user): bool {
		if (!$folder->acl || $this->subfolderQuotaManager->getDirectSubfolder($folder, $fileId) === null) {
			return false;
		}

		return $this->userMappingManager->userInMappings($user, $this->getManagersForSubfolder($folder->id, $fileId));
	}

	/**
	 * Check whether a delegate may administer a path within a Team folder.
	 * The assigned direct child and all of its descendants are covered.
	 */
	public function canManagePath(FolderDefinition $folder, string $path, IUser $user): bool {
		if (!$folder->acl) {
			return false;
		}

		$subfolder = $this->subfolderQuotaManager->getDirectSubfolderForPath($folder, $path);
		if ($subfolder === null) {
			return false;
		}

		return $this->userMappingManager->userInMappings($user, $this->getManagersForSubfolder($folder->id, $subfolder->fileId));
	}

	/**
	 * @throws \InvalidArgumentException when the child or mapping is invalid
	 */
	public function setManager(FolderDefinition $folder, int $fileId, string $mappingType, string $mappingId, bool $manager): IUserMapping {
		if (!$folder->acl) {
			throw new \InvalidArgumentException('Advanced permissions must be enabled before assigning a subfolder manager');
		}
		if ($this->subfolderQuotaManager->getDirectSubfolder($folder, $fileId) === null) {
			throw new \InvalidArgumentException('The subfolder manager can only be assigned to a direct child of this Team folder');
		}

		$mapping = $this->userMappingManager->mappingFromId($mappingType, $mappingId);
		if ($mapping === null) {
			throw new \InvalidArgumentException('The selected user, group, or team does not exist');
		}
		if (!$this->hasManagerTable()) {
			throw new \InvalidArgumentException('The subfolder manager database migration has not been applied yet');
		}

		if ($manager) {
			$this->addManager($folder->id, $fileId, $mapping);
		} else {
			$this->removeManager($folder->id, $fileId, $mapping);
		}

		$action = $manager ? 'given' : 'revoked';
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent('The %s "%s" was %s subfolder management rights for subfolder with id %d in groupfolder with id %d', [
			$mapping->getType(),
			$mapping->getId(),
			$action,
			$fileId,
			$folder->id,
		]));

		return $mapping;
	}

	public function hasManagers(int $folderId, int $fileId): bool {
		if (!$this->hasManagerTable()) {
			return false;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from('gf_subfolder_manager')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		return (int)$query->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Remove all manager mappings attached to a deleted or moved file cache entry.
	 */
	public function removeManagersByFileId(int $fileId): void {
		if (!$this->hasManagerTable()) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->delete('gf_subfolder_manager')
			->where($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}

	/**
	 * @return list<IUserMapping>
	 */
	private function getManagersForSubfolder(int $folderId, int $fileId): array {
		if (!$this->hasManagerTable()) {
			return [];
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('mapping_type', 'mapping_id')
			->from('gf_subfolder_manager')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->orderBy('mapping_type', 'ASC')
			->addOrderBy('mapping_id', 'ASC');

		/** @var list<array{mapping_type: string, mapping_id: string}> $rows */
		$rows = $query->executeQuery()->fetchAll();
		$mappings = [];
		foreach ($rows as $row) {
			$mapping = $this->userMappingManager->mappingFromId($row['mapping_type'], $row['mapping_id']);
			if ($mapping !== null) {
				$mappings[] = $mapping;
			}
		}

		return $mappings;
	}

	private function addManager(int $folderId, int $fileId, IUserMapping $mapping): void {
		$query = $this->connection->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from('gf_subfolder_manager')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($mapping->getType())))
			->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($mapping->getId())));
		if ((int)$query->executeQuery()->fetchOne() > 0) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->insert('gf_subfolder_manager')
			->values([
				'folder_id' => $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT),
				'file_id' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
				'mapping_type' => $query->createNamedParameter($mapping->getType()),
				'mapping_id' => $query->createNamedParameter($mapping->getId()),
			]);
		$query->executeStatement();
	}

	private function removeManager(int $folderId, int $fileId, IUserMapping $mapping): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('gf_subfolder_manager')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($mapping->getType())))
			->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($mapping->getId())));
		$query->executeStatement();
	}

	private function hasManagerTable(): bool {
		return $this->connection->tableExists('gf_subfolder_manager');
	}
}
