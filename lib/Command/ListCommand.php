<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Folder\FolderWithMappingsAndCache;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;
use OCP\Console\OutputFormat;
use OCP\Constants;
use OCP\IGroupManager;
use OCP\IUserManager;

#[AsCommand(
	name: 'groupfolders:list',
	description: 'List the configured Team folders',
	supportsOutputFormat: true,
)]
class ListCommand {
	/** @var array<int,string> */
	public const PERMISSION_NAMES = [
		Constants::PERMISSION_READ => 'read',
		Constants::PERMISSION_UPDATE => 'write',
		Constants::PERMISSION_SHARE => 'share',
		Constants::PERMISSION_DELETE => 'delete'
	];

	public function __construct(
		private readonly FolderManager $folderManager,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}

	public function __invoke(
		IOutput $output,
		OutputFormat $outputFormat,
		#[Option(description: 'List Team folders applicable for a user', shortcut: 'u')]
		?string $user = null,
	): ExitCode {
		$groups = $this->groupManager->search('');
		$groupNames = [];
		foreach ($groups as $group) {
			$groupNames[$group->getGID()] = $group->getDisplayName();
		}

		if ($user !== null) {
			$userObject = $this->userManager->get($user);
			if (!$userObject) {
				$output->writeln("<error>user $user not found</error>");
				return ExitCode::Failure;
			}

			$folders = $this->folderManager->getAllFoldersForUserWithSize($userObject);
		} else {
			$folders = $this->folderManager->getAllFoldersWithSize();
		}

		usort($folders, fn (FolderDefinition $a, FolderDefinition $b): int => $a->id - $b->id);

		if (count($folders) === 0) {
			if ($outputFormat === OutputFormat::Json || $outputFormat === OutputFormat::JsonPretty) {
				$output->writeln('[]');
			} else {
				$output->writeln('<info>No folders configured</info>');
			}

			return ExitCode::Success;
		}

		if ($outputFormat === OutputFormat::Json || $outputFormat === OutputFormat::JsonPretty) {
			$formatted = array_map(fn (FolderWithMappingsAndCache $folder): array => $folder->toArray(), $folders);
			foreach ($formatted as &$folder) {
				$folder['size'] = $folder['root_cache_entry']->getSize();
				unset($folder['root_cache_entry']);
				$folder['groups_list'] = array_map(fn (array $group): int => $group['permissions'], $folder['groups']);
				$folder['mountPoint'] = $folder['mount_point'];
				unset($folder['mount_point']);
				$folder['rootId'] = $folder['root_id'];
				unset($folder['root_id']);
				$folder['storageId'] = $folder['storage_id'];
				unset($folder['storage_id']);
			}

			$output->writeArrayInOutputFormat($formatted);
		} else {
			$rows = array_map(function (FolderWithMappingsAndCache $folder) use ($groupNames): array {
				$groupStrings = array_map(function (string $groupId, array $entry) use ($groupNames): string {
					[$permissions, $displayName] = [$entry['permissions'], $entry['displayName']];
					$groupName = array_key_exists($groupId, $groupNames) && ($groupNames[$groupId] !== $groupId) ? $groupNames[$groupId] . ' (' . $groupId . ')' : $displayName;

					return $groupName . ': ' . $this->permissionsToString($permissions);
				}, array_keys($folder->groups), array_values($folder->groups));
				$manageStrings = array_map(fn (array $manage): string => $manage['displayname'] . ' (' . $manage['type'] . ')', $folder->manage);

				return [
					'Folder Id' => $folder->id,
					'Name' => $folder->mountPoint,
					'Groups' => implode("\n", $groupStrings),
					'Quota' => ($folder->quota > 0) ? \OCP\Util::humanFileSize($folder->quota) : 'Unlimited',
					'Size' => $folder->rootCacheEntry->getSize(),
					'Advanced Permissions' => $folder->acl ? 'Enabled' : 'Disabled',
					'Manage advanced permissions' => implode("\n", $manageStrings),
				];
			}, $folders);
			$output->writeTableInOutputFormat($rows);
		}

		return ExitCode::Success;
	}

	private function permissionsToString(int $permissions): string {
		if ($permissions === 0) {
			return 'none';
		}

		return implode(', ', array_filter(self::PERMISSION_NAMES, fn (int $possiblePermission): bool => ($possiblePermission & $permissions) === $permissions, ARRAY_FILTER_USE_KEY));
	}
}
