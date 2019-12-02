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

namespace OCA\GroupFolders\DAV;

use OCA\DAV\Connector\Sabre\Node;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCP\Constants;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\Xml\Reader;

class ACLPlugin extends ServerPlugin {
	const ACL_ENABLED = '{http://nextcloud.org/ns}acl-enabled';
	const ACL_CAN_MANAGE = '{http://nextcloud.org/ns}acl-can-manage';
	const ACL_LIST = '{http://nextcloud.org/ns}acl-list';
	const INHERITED_ACL_LIST = '{http://nextcloud.org/ns}inherited-acl-list';
	const GROUP_FOLDER_ID = '{http://nextcloud.org/ns}group-folder-id';


	/** @var Server */
	private $server;

	private $ruleManager;
	private $folderManager;
	private $userSession;
	private $groupManager;
	/** @var IUser */
	private $user;

	public function __construct(
		RuleManager $ruleManager,
		IUserSession $userSession,
		IGroupManager $groupManager,
		FolderManager $folderManager
	) {
		$this->ruleManager = $ruleManager;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->folderManager = $folderManager;
	}

	private function isAdmin($path): bool {
		$folderId = $this->folderManager->getFolderByPath($path);
		return $this->folderManager->canManageACL($folderId, $this->user->getUID());
	}

	public function initialize(Server $server) {
		$this->server = $server;
		$this->user = $user = $this->userSession->getUser();

		$this->server->on('propFind', [$this, 'propFind']);
		$this->server->on('propPatch', [$this, 'propPatch']);

		$this->server->xml->elementMap[Rule::ACL] = Rule::class;
		$this->server->xml->elementMap[self::ACL_LIST] = function (Reader $reader) {
			return \Sabre\Xml\Deserializer\repeatingElements($reader, Rule::ACL);
		};
	}

	/**
	 * @param string $path
	 * @return string[]
	 */
	private function getParents(string $path): array {
		$paths = [];
		while ($path !== '') {
			$path = dirname($path);
			if ($path === '.' || $path === '/') {
				$path = '';
			}
			$paths[] = $path;
		}

		return $paths;
	}

	public function propFind(PropFind $propFind, INode $node) {
		if (!$node instanceof Node) {
			return;
		}

		$fileInfo = $node->getFileInfo();
		$mount = $fileInfo->getMountPoint();
		if (!$mount instanceof GroupMountPoint) {
			return;
		}

		$propFind->handle(self::ACL_LIST, function () use ($fileInfo, $mount) {
			$path = trim($mount->getSourcePath() . '/' . $fileInfo->getInternalPath(), '/');
			if ($this->isAdmin($fileInfo->getPath())) {
				$rules = $this->ruleManager->getAllRulesForPaths($mount->getNumericStorageId(), [$path]);
			} else {
				$rules = $this->ruleManager->getRulesForFilesByPath($this->user, $mount->getNumericStorageId(), [$path]);
			}
			return array_pop($rules);
		});

		$propFind->handle(self::INHERITED_ACL_LIST, function () use ($fileInfo, $mount) {
			$parentInternalPaths = $this->getParents($fileInfo->getInternalPath());
			$parentPaths = array_map(function (string $internalPath) use ($mount) {
				return trim($mount->getSourcePath() . '/' . $internalPath, '/');
			}, $parentInternalPaths);
			if ($this->isAdmin($fileInfo->getPath())) {
				$rulesByPath = $this->ruleManager->getAllRulesForPaths($mount->getNumericStorageId(), $parentPaths);
			} else {
				$rulesByPath = $this->ruleManager->getRulesForFilesByPath($this->user, $mount->getNumericStorageId(), $parentPaths);
			}

			ksort($rulesByPath);
			$inheritedPermissionsByMapping = [];
			$mappings = [];
			foreach ($rulesByPath as $rules) {
				foreach ($rules as $rule) {
					/** @var Rule $rule */
					$mappingKey = $rule->getUserMapping()->getType() . '::' . $rule->getUserMapping()->getId();
					if (!isset($mappings[$mappingKey])) {
						$mappings[$mappingKey] = $rule->getUserMapping();
					}
					if (!isset($inheritedPermissionsByMapping[$mappingKey])) {
						$inheritedPermissionsByMapping[$mappingKey] = Constants::PERMISSION_ALL;
					}
					$inheritedPermissionsByMapping[$mappingKey] = $rule->applyPermissions($inheritedPermissionsByMapping[$mappingKey]);
				}
			}

			return array_map(function ($mapping, $permissions) use ($fileInfo) {
				return new Rule(
					$mapping,
					$fileInfo->getId(),
					Constants::PERMISSION_ALL,
					$permissions
				);
			}, $mappings, $inheritedPermissionsByMapping);
		});

		$propFind->handle(self::GROUP_FOLDER_ID, function () use ($fileInfo) {
			return $this->folderManager->getFolderByPath($fileInfo->getPath());
		});

		$propFind->handle(self::ACL_ENABLED, function () use ($fileInfo) {
			$folderId = $this->folderManager->getFolderByPath($fileInfo->getPath());
			$folder = $this->folderManager->getFolder($folderId, -1);
			return $folder['acl'];
		});

		$propFind->handle(self::ACL_CAN_MANAGE, function () use ($fileInfo) {
			return $this->isAdmin($fileInfo->getPath());
		});
	}

	function propPatch($path, PropPatch $propPatch) {
		$node = $this->server->tree->getNodeForPath($path);
		if (!$node instanceof Node) {
			return;
		}
		$fileInfo = $node->getFileInfo();
		$mount = $fileInfo->getMountPoint();
		if (!$mount instanceof GroupMountPoint || !$this->isAdmin($fileInfo->getPath())) {
			return;
		}

		// Mapping the old property to the new property.
		$propPatch->handle(self::ACL_LIST, function (array $rawRules) use ($path) {
			$node = $this->server->tree->getNodeForPath($path);
			if (!$node instanceof Node) {
				return false;
			}
			$fileInfo = $node->getFileInfo();
			$mount = $fileInfo->getMountPoint();
			if (!$mount instanceof GroupMountPoint) {
				return false;
			}
			$path = trim($mount->getSourcePath() . '/' . $fileInfo->getInternalPath(), '/');

			// populate fileid in rules
			$rules = array_map(function (Rule $rule) use ($fileInfo) {
				return new Rule(
					$rule->getUserMapping(),
					$fileInfo->getId(),
					$rule->getMask(),
					$rule->getPermissions()
				);
			}, $rawRules);

			$existingRules = array_reduce(
				$this->ruleManager->getAllRulesForPaths($mount->getNumericStorageId(), [$path]),
				function (array $rules, array $rulesForPath) {
					return array_merge($rules, $rulesForPath);
				},
				[]
			);


			$deletedRules = array_udiff($existingRules, $rules, function ($obj_a, $obj_b) {
				return (
					$obj_a->getUserMapping()->getType() === $obj_b->getUserMapping()->getType() &&
					$obj_a->getUserMapping()->getId() === $obj_b->getUserMapping()->getId()
				) ? 0 : -1;
			});
			foreach ($deletedRules as $deletedRule) {
				$this->ruleManager->deleteRule($deletedRule);
			}

			foreach ($rules as $rule) {
				$this->ruleManager->saveRule($rule);
			}

			return true;
		});

	}
}
