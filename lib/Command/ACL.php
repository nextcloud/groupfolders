<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use InvalidArgumentException;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;
use OCP\Console\OutputFormat;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use RuntimeException;

#[AsCommand(
	name: 'groupfolders:permissions',
	description: 'Configure advanced permissions for a configured Team folder',
	supportsOutputFormat: true,
)]
class ACL extends FolderCommand {
	public function __construct(
		FolderManager $folderManager,
		IRootFolder $rootFolder,
		MountProvider $mountProvider,
		FolderStorageManager $folderStorageManager,
		private readonly RuleManager $ruleManager,
		private readonly ACLManagerFactory $aclManagerFactory,
		private readonly IUserManager $userManager,
	) {
		parent::__construct($folderManager, $rootFolder, $mountProvider, $folderStorageManager);
	}

	/**
	 * @param list<string> $permissions
	 */
	public function __invoke(
		IOutput $output,
		OutputFormat $outputFormat,
		#[Argument(name: 'folder_id', description: 'Id of the folder to configure')]
		string $folderId,
		#[Argument(description: 'The path within the folder to set permissions for')]
		?string $path = null,
		#[Argument(description: 'The permissions to set for the user or group as a white space separated list (ex: +read "-write"). Use "clear" to remove all permissions. Prepend the permission list with -- to allow parsing the - character.')]
		array $permissions = [],
		#[Option(description: 'Enable advanced permissions for the folder', shortcut: 'e')]
		bool $enable = false,
		#[Option(description: 'Disable advanced permissions for the folder', shortcut: 'd')]
		bool $disable = false,
		#[Option(name: 'manage-add', description: 'Add manage permission for user or group', shortcut: 'm')]
		bool $manageAdd = false,
		#[Option(name: 'manage-remove', description: 'Remove manage permission for user or group', shortcut: 'r')]
		bool $manageRemove = false,
		#[Option(description: 'The user to configure the permissions for', shortcut: 'u')]
		?string $user = null,
		#[Option(description: 'The group to configure the permissions for', shortcut: 'g')]
		?string $group = null,
		#[Option(description: 'The circle/team to configure the permissions for', shortcut: 'c')]
		?string $team = null,
		#[Option(description: 'Test the permissions for the set path', shortcut: 't')]
		bool $test = false,
	): ExitCode|int {
		$folder = $this->getFolder($folderId, $output);
		if ($folder === null) {
			return -1;
		}

		if ($enable) {
			$this->folderManager->setFolderACL($folder->id, true);
		} elseif ($disable) {
			$this->folderManager->setFolderACL($folder->id, false);
		} elseif ($test) {
			if ($user && $path) {
				$userObject = $this->userManager->get($user);
				if (!$userObject) {
					$output->writeln('<error>User not found: ' . $user . '</error>');
					return -1;
				}

				$aclManager = $this->aclManagerFactory->getACLManager($userObject);
				if ($this->folderManager->getFolderPermissionsForUser($userObject, $folder->id) === 0) {
					$folderPermissions = 0;
				} else {
					$folderPermissions = $aclManager->getACLPermissionsForPath($folder->id, $folder->storageId, $folder->rootCacheEntry->getPath() . rtrim('/' . $path, '/'));
				}
				$permissionString = Rule::formatRulePermissions(Constants::PERMISSION_ALL, $folderPermissions);
				$output->writeln($permissionString);

				return ExitCode::Success;
			} else {
				$output->writeln('<error>--user and <path> options needs to be set for permissions testing</error>');
				return -3;
			}
		} elseif (!$folder->acl) {
			$output->writeln('<error>Advanced permissions not enabled for folder: ' . $folder->id . '</error>');
			return -2;
		} elseif (!$path && !$permissions && !$user && !$team && !$group) {
			$this->printPermissions($output, $outputFormat, $folder);
		} elseif ($manageAdd && ($user || $group || $team)) {
			[$mappingType, $mappingId] = $this->convertMappingOptions($user, $group, $team);
			$this->folderManager->setManageACL($folder->id, $mappingType, $mappingId, true);
		} elseif ($manageRemove && ($user || $group || $team)) {
			[$mappingType, $mappingId] = $this->convertMappingOptions($user, $group, $team);
			$this->folderManager->setManageACL($folder->id, $mappingType, $mappingId, false);
		} elseif (!$path) {
			$output->writeln('<error><path> argument has to be set when not using --enable or --disable</error>');
			return -3;
		} elseif (!$permissions) {
			$output->writeln('<error><permissions> argument has to be set when not using --enable or --disable</error>');
			return -3;
		} elseif ((int)(bool)$user + (int)(bool)$group + (int)(bool)$team > 1) {
			$output->writeln('<error>--user, --team and --group can not be used at the same time</error>');
			return -3;
		} elseif (!$user && !$group && !$team) {
			$output->writeln('<error>either --user, --group or --team has to be used when not using --enable or --disable</error>');
			return -3;
		} else {
			[$mappingType, $mappingId] = $this->convertMappingOptions($user, $group, $team);
			$path = trim($path, '/');
			/** @var list<string> $permissionStrings */
			$permissionStrings = $permissions;

			$mount = $this->mountProvider->getMount(
				FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL),
				'/dummy/files/' . $folder->mountPoint,
			);

			$storage = $mount->getStorage();
			if ($storage === null) {
				throw new RuntimeException('Failed to get storage for mount.');
			}

			$id = $storage->getCache()->getId($path);
			if ($id === -1) {
				$output->writeln('<error>Path not found in folder: ' . $path . '</error>');
				return -1;
			}

			if ($permissionStrings === ['clear']) {
				$this->ruleManager->deleteRule(new Rule(
					new UserMapping($mappingType, $mappingId),
					$id,
					0,
					0
				));

				return ExitCode::Success;
			}

			foreach ($permissionStrings as $permission) {
				if ($permission[0] !== '+' && $permission[0] !== '-') {
					$output->writeln('<error>incorrect format for permissions "' . $permission . '"</error>');
					return -3;
				}

				$name = substr($permission, 1);
				if (!isset(Rule::PERMISSIONS_MAP[$name])) {
					$output->writeln('<error>incorrect format for permissions2 "' . $permission . '"</error>');
					return -3;
				}
			}

			[$mask, $rulePermissions] = $this->parsePermissions($permissionStrings);

			$this->ruleManager->saveRule(new Rule(
				new UserMapping($mappingType, $mappingId),
				$id,
				$mask,
				$rulePermissions
			));
		}

		return ExitCode::Success;
	}

	private function printPermissions(IOutput $output, OutputFormat $outputFormat, FolderWithMappingsAndCache $folder): void {
		$rootPath = $folder->rootCacheEntry->getPath();
		$rules = $this->ruleManager->getAllRulesForPrefix(
			$folder->storageId,
			$rootPath
		);
		$jailPathLength = strlen($rootPath) + 1;

		if ($outputFormat === OutputFormat::Json || $outputFormat === OutputFormat::JsonPretty) {
			$paths = array_map(function (string $rawPath) use ($jailPathLength): string {
				$path = substr($rawPath, $jailPathLength);
				return $path ?: '/';
			}, array_keys($rules));
			$items = array_combine($paths, $rules);
			ksort($items);

			$output->writeln(json_encode($items, JSON_THROW_ON_ERROR | ($outputFormat === OutputFormat::JsonPretty ? JSON_PRETTY_PRINT : 0)));
		} else {
			$rows = array_map(function (array $rulesForPath, string $path) use ($jailPathLength): array {
				/** @var Rule[] $rulesForPath */
				$mappings = array_map(
					fn (Rule $rule): string
						=> $rule->getUserMapping()->getType()
							. ': ' . $rule->getUserMapping()->getDisplayName() . ' (' . $rule->getUserMapping()->getId() . ')',
					$rulesForPath,
				);
				$rulePermissions = array_map(fn (Rule $rule): string => $rule->formatPermissions(), $rulesForPath);
				$formattedPath = substr($path, $jailPathLength);

				return [
					'Path' => $formattedPath ?: '/',
					'User/Group' => implode("\n", $mappings),
					'Permissions' => implode("\n", $rulePermissions),
				];
			}, $rules, array_keys($rules));
			usort($rows, fn (array $a, array $b): int => $a['Path'] <=> $b['Path']);

			$output->writeTableInOutputFormat($rows);
		}
	}

	/**
	 * @return array{'user'|'group'|'circle', string}
	 */
	private function convertMappingOptions(?string $user, ?string $group, ?string $team): array {
		if ($user !== null) {
			return ['user', $user];
		}

		if ($group !== null) {
			return ['group', $group];
		}

		if ($team !== null) {
			return ['circle', $team];
		}

		throw new InvalidArgumentException('invalid mapping options');
	}

	/**
	 * @param list<string> $permissions
	 * @return array{int, int}
	 */
	private function parsePermissions(array $permissions): array {
		$mask = 0;
		$result = 0;

		foreach ($permissions as $permission) {
			$permissionValue = Rule::PERMISSIONS_MAP[substr($permission, 1)];
			$mask |= $permissionValue;
			if ($permission[0] === '+') {
				$result |= $permissionValue;
			}
		}

		return [$mask, $result];
	}
}
