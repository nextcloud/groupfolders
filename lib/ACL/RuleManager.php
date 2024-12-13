<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Log\Audit\CriticalActionPerformedEvent;

class RuleManager {
	private IDBConnection $connection;
	private IUserMappingManager $userMappingManager;
	private IEventDispatcher $eventDispatcher;

	public function __construct(IDBConnection $connection, IUserMappingManager $userMappingManager, IEventDispatcher $eventDispatcher) {
		$this->connection = $connection;
		$this->userMappingManager = $userMappingManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	private function createRule(array $data): ?Rule {
		$mapping = $this->userMappingManager->mappingFromId($data['mapping_type'], $data['mapping_id']);
		if ($mapping) {
			return new Rule(
				$mapping,
				(int)$data['fileid'],
				(int)$data['mask'],
				(int)$data['permissions']
			);
		} else {
			return null;
		}
	}

	/**
	 * @param IUser $user
	 * @param int[] $fileIds
	 * @return (Rule[])[] [$fileId => Rule[]]
	 */
	public function getRulesForFilesById(IUser $user, array $fileIds): array {
		$userMappings = $this->userMappingManager->getMappingsForUser($user);

		$query = $this->connection->getQueryBuilder();
		$query->select(['fileid', 'mapping_type', 'mapping_id', 'mask', 'permissions'])
			->from('group_folders_acl')
			->where($query->expr()->in('fileid', $query->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($query->expr()->orX(...array_map(function (IUserMapping $userMapping) use ($query) {
				return $query->expr()->andX(
					$query->expr()->eq('mapping_type', $query->createNamedParameter($userMapping->getType())),
					$query->expr()->eq('mapping_id', $query->createNamedParameter($userMapping->getId()))
				);
			}, $userMappings)));

		$rows = $query->executeQuery()->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			if (!isset($result[$row['fileid']])) {
				$result[$row['fileid']] = [];
			}
			$rule = $this->createRule($row);
			if ($rule) {
				$result[$row['fileid']][] = $rule;
			}
		}
		return $result;
	}

	/**
	 * @param IUser $user
	 * @param int $storageId
	 * @param string[] $filePaths
	 * @return (Rule[])[] [$path => Rule[]]
	 */
	public function getRulesForFilesByPath(IUser $user, int $storageId, array $filePaths): array {
		$userMappings = $this->userMappingManager->getMappingsForUser($user);

		$hashes = array_map(function (string $path): string {
			return md5(trim($path, '/'));
		}, $filePaths);

		$rows = [];
		foreach (array_chunk($hashes, 1000) as $chunk) {
			$query = $this->connection->getQueryBuilder();
			$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'f.path'])
				->from('group_folders_acl', 'a')
				->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
				->where($query->expr()->in('f.path_hash', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($query->expr()->eq('f.storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->orX(...array_map(function (IUserMapping $userMapping) use ($query) {
					return $query->expr()->andX(
						$query->expr()->eq('mapping_type', $query->createNamedParameter($userMapping->getType())),
						$query->expr()->eq('mapping_id', $query->createNamedParameter($userMapping->getId()))
					);
				}, $userMappings)));

			$rows = array_merge($rows, $query->executeQuery()->fetchAll());
		}


		$result = [];
		foreach ($filePaths as $path) {
			$result[$path] = [];
		}
		return $this->rulesByPath($rows, $result);
	}

	/**
	 * @param IUser $user
	 * @param int $storageId
	 * @param string $parent
	 * @return (Rule[])[] [$path => Rule[]]
	 */
	public function getRulesForFilesByParent(IUser $user, int $storageId, string $parent): array {
		$userMappings = $this->userMappingManager->getMappingsForUser($user);

		$parentId = $this->getId($storageId, $parent);
		if (!$parentId) {
			return [];
		}

		$query = $this->connection->getQueryBuilder();
		$query->select(['f.fileid', 'a.mapping_type', 'a.mapping_id', 'a.mask', 'a.permissions', 'f.path'])
			->from('filecache', 'f')
			->leftJoin('f', 'group_folders_acl', 'a', $query->expr()->eq('f.fileid', 'a.fileid'))
			->andWhere($query->expr()->eq('f.parent', $query->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('f.storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere(
				$query->expr()->orX(
					$query->expr()->andX(
						$query->expr()->isNull('a.mapping_type'),
						$query->expr()->isNull('a.mapping_id')
					),
					...array_map(function (IUserMapping $userMapping) use ($query) {
						return $query->expr()->andX(
							$query->expr()->eq('a.mapping_type', $query->createNamedParameter($userMapping->getType())),
							$query->expr()->eq('a.mapping_id', $query->createNamedParameter($userMapping->getId()))
						);
					}, $userMappings)
				)
			);

		$rows = $query->executeQuery()->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			if (!isset($result[$row['path']])) {
				$result[$row['path']] = [];
			}
			if ($row['mapping_type'] !== null) {
				$rule = $this->createRule($row);
				if ($rule) {
					$result[$row['path']][] = $rule;
				}
			}
		}
		return $result;
	}

	private function getId(int $storageId, string $path): int {
		$query = $this->connection->getQueryBuilder();
		$query->select(['fileid'])
			->from('filecache')
			->where($query->expr()->eq('path_hash', $query->createNamedParameter(md5($path), IQueryBuilder::PARAM_STR)))
			->andWhere($query->expr()->eq('storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)));

		return (int)$query->executeQuery()->fetch(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param int $storageId
	 * @param string[] $filePaths
	 * @return (Rule[])[] [$path => Rule[]]
	 */
	public function getAllRulesForPaths(int $storageId, array $filePaths): array {
		$hashes = array_map(function (string $path) {
			return md5(trim($path, '/'));
		}, $filePaths);
		$query = $this->connection->getQueryBuilder();
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'f.path'])
			->from('group_folders_acl', 'a')
			->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
			->where($query->expr()->in('f.path_hash', $query->createNamedParameter($hashes, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->eq('f.storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)));

		$rows = $query->executeQuery()->fetchAll();

		return $this->rulesByPath($rows);
	}

	private function rulesByPath(array $rows, array $result = []): array {
		foreach ($rows as $row) {
			if (!isset($result[$row['path']])) {
				$result[$row['path']] = [];
			}
			$rule = $this->createRule($row);
			if ($rule) {
				$result[$row['path']][] = $rule;
			}
		}

		ksort($result);

		return $result;
	}

	/**
	 * @param int $storageId
	 * @param string $prefix
	 * @return (Rule[])[] [$path => Rule[]]
	 */
	public function getAllRulesForPrefix(int $storageId, string $prefix): array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'f.path'])
			->from('group_folders_acl', 'a')
			->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
			->where($query->expr()->orX(
				$query->expr()->like('f.path', $query->createNamedParameter($this->connection->escapeLikeParameter($prefix) . '/%')),
				$query->expr()->eq('f.path_hash', $query->createNamedParameter(md5($prefix)))
			))
			->andWhere($query->expr()->eq('f.storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)));

		$rows = $query->executeQuery()->fetchAll();

		return $this->rulesByPath($rows);
	}

	/**
	 * @param IUser $user
	 * @param int $storageId
	 * @param string $prefix
	 * @return array (Rule[])[] [$path => Rule[]]
	 */
	public function getRulesForPrefix(IUser $user, int $storageId, string $prefix): array {
		$userMappings = $this->userMappingManager->getMappingsForUser($user);

		$query = $this->connection->getQueryBuilder();
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'f.path'])
			->from('group_folders_acl', 'a')
			->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
			->where($query->expr()->orX(
				$query->expr()->like('f.path', $query->createNamedParameter($this->connection->escapeLikeParameter($prefix) . '/%')),
				$query->expr()->eq('f.path_hash', $query->createNamedParameter(md5($prefix)))
			))
			->andWhere($query->expr()->eq('f.storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->orX(...array_map(function (IUserMapping $userMapping) use ($query) {
				return $query->expr()->andX(
					$query->expr()->eq('mapping_type', $query->createNamedParameter($userMapping->getType())),
					$query->expr()->eq('mapping_id', $query->createNamedParameter($userMapping->getId()))
				);
			}, $userMappings)));

		$rows = $query->executeQuery()->fetchAll();

		return $this->rulesByPath($rows);
	}

	private function hasRule(IUserMapping $mapping, int $fileId): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('fileid')
			->from('group_folders_acl')
			->where($query->expr()->eq('fileid', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($mapping->getType())))
			->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($mapping->getId())));
		return (bool)$query->executeQuery()->fetch();
	}

	public function saveRule(Rule $rule): void {
		if ($this->hasRule($rule->getUserMapping(), $rule->getFileId())) {
			$query = $this->connection->getQueryBuilder();
			$query->update('group_folders_acl')
				->set('mask', $query->createNamedParameter($rule->getMask(), IQueryBuilder::PARAM_INT))
				->set('permissions', $query->createNamedParameter($rule->getPermissions(), IQueryBuilder::PARAM_INT))
				->where($query->expr()->eq('fileid', $query->createNamedParameter($rule->getFileId(), IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($rule->getUserMapping()->getType())))
				->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($rule->getUserMapping()->getId())));
			$query->executeStatement();

			if ($rule->getUserMapping()->getType() === 'user') {
				$logMessage = 'The ACL rule was updated to permission "%s" and mask "%s" for file/folder with id "%s" for user "%s"';
				$params = [
					'permissions' => $rule->getPermissions(),
					'mask' => $rule->getMask(),
					'fileId' => $rule->getFileId(),
					'user' => $rule->getUserMapping()->getDisplayName() . ' (' . $rule->getUserMapping()->getId() . ')',
				];
			} else {
				$logMessage = 'The ACL rule was updated to permission "%s" and mask "%s" for file/folder with id "%s" for group "%s"';
				$params = [
					'permissions' => $rule->getPermissions(),
					'mask' => $rule->getMask(),
					'fileId' => $rule->getFileId(),
					'user' => $rule->getUserMapping()->getDisplayName(),
				];
			}

			$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent($logMessage, $params));
		} else {
			$query = $this->connection->getQueryBuilder();
			$query->insert('group_folders_acl')
				->values([
					'fileid' => $query->createNamedParameter($rule->getFileId(), IQueryBuilder::PARAM_INT),
					'mapping_type' => $query->createNamedParameter($rule->getUserMapping()->getType()),
					'mapping_id' => $query->createNamedParameter($rule->getUserMapping()->getId()),
					'mask' => $query->createNamedParameter($rule->getMask(), IQueryBuilder::PARAM_INT),
					'permissions' => $query->createNamedParameter($rule->getPermissions(), IQueryBuilder::PARAM_INT)
				]);
			$query->executeStatement();

			if ($rule->getUserMapping()->getType() === 'user') {
				$logMessage = 'A new ACL rule was created to permission "%s" and mask "%s" for file/folder with id "%s" for user "%s"';
				$params = [
					'permissions' => $rule->getPermissions(),
					'mask' => $rule->getMask(),
					'fileId' => $rule->getFileId(),
					'user' => $rule->getUserMapping()->getDisplayName() . ' (' . $rule->getUserMapping()->getId() . ')',
				];
			} else {
				$logMessage = 'A new ACL rule was created to permission "%s" and mask "%s" for file/folder with id "%s" for group "%s"';
				$params = [
					'permissions' => $rule->getPermissions(),
					'mask' => $rule->getMask(),
					'fileId' => $rule->getFileId(),
					'group' => $rule->getUserMapping()->getDisplayName(),
				];
			}

			$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent($logMessage, $params));
		}
	}

	public function deleteRule(Rule $rule): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_acl')
			->where($query->expr()->eq('fileid', $query->createNamedParameter($rule->getFileId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($rule->getUserMapping()->getType())))
			->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($rule->getUserMapping()->getId())));
		$query->executeStatement();

		if ($rule->getUserMapping()->getType() === 'user') {
			$logMessage = 'The ACL rule was deleted for file/folder with id: "%s" for the user "%s"';
			$params = [
				'fileId' => $rule->getFileId(),
				'user' => $rule->getUserMapping()->getDisplayName() . ' (' . $rule->getUserMapping()->getId() . ')',
			];
		} else {
			$logMessage = 'The ACL rule was deleted for file/folder with id: "%s" for the group "%s"';
			$params = [
				'fileId' => $rule->getFileId(),
				'group' => $rule->getUserMapping()->getDisplayName(),
			];
		}

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent($logMessage, $params));
	}
}
