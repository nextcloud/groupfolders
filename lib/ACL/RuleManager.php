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

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;

class RuleManager {
	private $connection;
	private $userMappingManager;

	public function __construct(IDBConnection $connection, IUserMappingManager $userMappingManager) {
		$this->connection = $connection;
		$this->userMappingManager = $userMappingManager;
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

		$rows = $query->execute()->fetchAll();

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

		$hashes = array_map(function (string $path) {
			return md5($path);
		}, $filePaths);

		$query = $this->connection->getQueryBuilder();
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'path'])
			->from('group_folders_acl', 'a')
			->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
			->where($query->expr()->in('path_hash', $query->createNamedParameter($hashes, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->eq('storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->orX(...array_map(function (IUserMapping $userMapping) use ($query) {
				return $query->expr()->andX(
					$query->expr()->eq('mapping_type', $query->createNamedParameter($userMapping->getType())),
					$query->expr()->eq('mapping_id', $query->createNamedParameter($userMapping->getId()))
				);
			}, $userMappings)));

		$rows = $query->execute()->fetchAll();

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
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'path'])
			->from('filecache', 'f')
			->leftJoin('f', 'group_folders_acl', 'a', $query->expr()->eq('f.fileid', 'a.fileid'))
			->andWhere($query->expr()->eq('parent', $query->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)))
			->andWhere(
				$query->expr()->orX(
					$query->expr()->andX(
						$query->expr()->isNull('mapping_type'),
						$query->expr()->isNull('mapping_id')
					),
					...array_map(function (IUserMapping $userMapping) use ($query) {
						return $query->expr()->andX(
							$query->expr()->eq('mapping_type', $query->createNamedParameter($userMapping->getType())),
							$query->expr()->eq('mapping_id', $query->createNamedParameter($userMapping->getId()))
						);
					}, $userMappings)
				)
			);

		$rows = $query->execute()->fetchAll();

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

		return (int)$query->execute()->fetch(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param int $storageId
	 * @param string[] $filePaths
	 * @return (Rule[])[] [$path => Rule[]]
	 */
	public function getAllRulesForPaths(int $storageId, array $filePaths): array {
		$hashes = array_map(function (string $path) {
			return md5($path);
		}, $filePaths);
		$query = $this->connection->getQueryBuilder();
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'path'])
			->from('group_folders_acl', 'a')
			->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
			->where($query->expr()->in('path_hash', $query->createNamedParameter($hashes, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->eq('storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)));

		$rows = $query->execute()->fetchAll();

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
		return $result;
	}

	/**
	 * @param int $storageId
	 * @param string $prefix
	 * @return (Rule[])[] [$path => Rule[]]
	 */
	public function getAllRulesForPrefix(int $storageId, string $prefix): array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'path'])
			->from('group_folders_acl', 'a')
			->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
			->where($query->expr()->orX(
				$query->expr()->like('path', $query->createNamedParameter($this->connection->escapeLikeParameter($prefix) . '/%')),
				$query->expr()->eq('path_hash', $query->createNamedParameter(md5($prefix)))
			))
			->andWhere($query->expr()->eq('storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)));

		$rows = $query->execute()->fetchAll();

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
		$query->select(['f.fileid', 'mapping_type', 'mapping_id', 'mask', 'a.permissions', 'path'])
			->from('group_folders_acl', 'a')
			->innerJoin('a', 'filecache', 'f', $query->expr()->eq('f.fileid', 'a.fileid'))
			->where($query->expr()->orX(
				$query->expr()->like('path', $query->createNamedParameter($this->connection->escapeLikeParameter($prefix) . '/%')),
				$query->expr()->eq('path_hash', $query->createNamedParameter(md5($prefix)))
			))
			->andWhere($query->expr()->eq('storage', $query->createNamedParameter($storageId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->orX(...array_map(function (IUserMapping $userMapping) use ($query) {
				return $query->expr()->andX(
					$query->expr()->eq('mapping_type', $query->createNamedParameter($userMapping->getType())),
					$query->expr()->eq('mapping_id', $query->createNamedParameter($userMapping->getId()))
				);
			}, $userMappings)));

		$rows = $query->execute()->fetchAll();

		return $this->rulesByPath($rows);
	}

	private function hasRule(IUserMapping $mapping, int $fileId): bool {
		$query = $this->connection->getQueryBuilder();
		$query->select('fileid')
			->from('group_folders_acl')
			->where($query->expr()->eq('fileid', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($mapping->getType())))
			->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($mapping->getId())));
		return (bool)$query->execute()->fetch();
	}

	public function saveRule(Rule $rule) {
		if ($this->hasRule($rule->getUserMapping(), $rule->getFileId())) {
			$query = $this->connection->getQueryBuilder();
			$query->update('group_folders_acl')
				->set('mask', $query->createNamedParameter($rule->getMask(), IQueryBuilder::PARAM_INT))
				->set('permissions', $query->createNamedParameter($rule->getPermissions(), IQueryBuilder::PARAM_INT))
				->where($query->expr()->eq('fileid', $query->createNamedParameter($rule->getFileId(), IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($rule->getUserMapping()->getType())))
				->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($rule->getUserMapping()->getId())));
			$query->execute();
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
			$query->execute();
		}
	}

	public function deleteRule(Rule $rule) {
		$query = $this->connection->getQueryBuilder();
		$query->delete('group_folders_acl')
			->where($query->expr()->eq('fileid', $query->createNamedParameter($rule->getFileId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('mapping_type', $query->createNamedParameter($rule->getUserMapping()->getType())))
			->andWhere($query->expr()->eq('mapping_id', $query->createNamedParameter($rule->getUserMapping()->getId())));
		$query->execute();
	}
}
