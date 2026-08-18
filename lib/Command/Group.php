<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;

#[AsCommand(
	name: 'groupfolders:group',
	description: 'Edit the groups that have access to a Team folder',
)]
class Group extends FolderCommand {
	public const PERMISSION_VALUES = [
		'read' => Constants::PERMISSION_READ,
		'write' => Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE,
		'share' => Constants::PERMISSION_SHARE,
		'delete' => Constants::PERMISSION_DELETE,
	];

	public function __construct(
		FolderManager $folderManager,
		IRootFolder $rootFolder,
		MountProvider $mountProvider,
		FolderStorageManager $folderStorageManager,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct($folderManager, $rootFolder, $mountProvider, $folderStorageManager);
	}

	/**
	 * @param list<string> $permissions
	 */
	public function __invoke(
		IOutput $output,
		#[Argument(name: 'folder_id', description: 'Id of the folder to configure')]
		string $folderId,
		#[Argument(description: 'The group to configure')]
		string $group,
		#[Argument(description: 'The permissions to set for the group as a white space separated list (ex: read write). Leave empty for read only')]
		array $permissions = [],
		#[Option(description: 'Remove access for the group', shortcut: 'd')]
		bool $delete = false,
	): ExitCode|int {
		$folder = $this->getFolder($folderId, $output);
		if ($folder === null) {
			return -1;
		}

		$groupObject = $this->groupManager->get($group);
		if ($delete) {
			$this->folderManager->removeApplicableGroup($folder->id, $group);
			return ExitCode::Success;
		} elseif ($groupObject || $this->folderManager->isACircle($group)) {
			$newPermissions = $this->getNewPermissions($permissions);
			if ($newPermissions > 0) {
				if (!isset($folder->groups[$group])) {
					$this->folderManager->addApplicableGroup($folder->id, $group);
				}

				$this->folderManager->setGroupPermissions($folder->id, $group, $newPermissions);
				return ExitCode::Success;
			}

			$output->writeln('<error>Unable to parse permissions input: ' . implode(' ', $permissions) . '</error>');

			return -1;
		}

		$output->writeln('<error>group/team not found: ' . $group . '</error>');

		return -1;
	}

	/**
	 * @param list<string> $input
	 */
	private function getNewPermissions(array $input): int {
		$permissions = 1;
		$values = self::PERMISSION_VALUES;
		foreach ($input as $permissionsString) {
			if (isset($values[$permissionsString])) {
				$permissions |= self::PERMISSION_VALUES[$permissionsString];
			} else {
				return 0;
			}
		}

		return $permissions;
	}
}
