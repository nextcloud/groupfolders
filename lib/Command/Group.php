<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Command;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Group extends FolderCommand {
	public const PERMISSION_VALUES = [
		'read' => Constants::PERMISSION_READ,
		'write' => Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE,
		'share' => Constants::PERMISSION_SHARE,
		'delete' => Constants::PERMISSION_DELETE,
	];
	private IGroupManager $groupManager;

	public function __construct(FolderManager $folderManager, IRootFolder $rootFolder, IGroupManager $groupManager, MountProvider $mountProvider) {
		parent::__construct($folderManager, $rootFolder, $mountProvider);
		$this->groupManager = $groupManager;
	}

	protected function configure() {
		$this
			->setName('groupfolders:group')
			->setDescription('Edit the groups that have access to a group folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to configure')
			->addArgument('group', InputArgument::REQUIRED, 'The group to configure')
			->addArgument('permissions', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The permissions to set for the group, leave empty for read only')
			->addOption('delete', 'd', InputOption::VALUE_NONE, 'Remove access for the group');

		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$folder = $this->getFolder($input, $output);
		if ($folder === false) {
			return -1;
		}
		$groupString = $input->getArgument('group');
		$group = $this->groupManager->get($groupString);
		if ($input->getOption('delete')) {
			$this->folderManager->removeApplicableGroup($folder['id'], $groupString);
			return 0;
		} elseif ($group) {
			$permissionsString = $input->getArgument('permissions');
			$permissions = $this->getNewPermissions($permissionsString);
			if ($permissions) {
				if (!isset($folder['groups'][$groupString])) {
					$this->folderManager->addApplicableGroup($folder['id'], $groupString);
				}
				$this->folderManager->setGroupPermissions($folder['id'], $groupString, $permissions);
				return 0;
			}
			$output->writeln('<error>Unable to parse permissions input: ' . implode(' ', $permissionsString) . '</error>');
			return -1;
		}
		$output->writeln('<error>group not found: ' . $groupString . '</error>');
		return -1;
	}

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
