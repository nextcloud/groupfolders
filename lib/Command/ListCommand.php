<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {
	/** @var array<int,string> */
	public const PERMISSION_NAMES = [
		Constants::PERMISSION_READ => 'read',
		Constants::PERMISSION_UPDATE => 'write',
		Constants::PERMISSION_SHARE => 'share',
		Constants::PERMISSION_DELETE => 'delete'
	];

	private FolderManager $folderManager;
	private IRootFolder $rootFolder;
	private IGroupManager $groupManager;
	private IUserManager $userManager;

	public function __construct(FolderManager $folderManager, IRootFolder $rootFolder, IGroupManager $groupManager, IUserManager $userManager) {
		parent::__construct();
		$this->folderManager = $folderManager;
		$this->rootFolder = $rootFolder;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}

	protected function configure() {
		$this
			->setName('groupfolders:list')
			->setDescription('List the configured group folders')
			->addOption('user', 'u', InputArgument::OPTIONAL, "List group folders applicable for a user");
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getOption('user');
		$groups = $this->groupManager->search('');
		$groupNames = [];
		foreach ($groups as $group) {
			$groupNames[$group->getGID()] = $group->getDisplayName();
		}

		$rootStorageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($userId) {
			$user = $this->userManager->get($userId);
			if (!$user) {
				$output->writeln("<error>user $userId not found</error>");
				return 1;
			}
			$folders = $this->folderManager->getAllFoldersForUserWithSize($rootStorageId, $user);
		} else {
			$folders = $this->folderManager->getAllFoldersWithSize($rootStorageId);
		}
		usort($folders, function ($a, $b) {
			return $a['id'] - $b['id'];
		});

		$outputType = $input->getOption('output');
		if (count($folders) === 0) {
			if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
				$output->writeln('[]');
			} else {
				$output->writeln("<info>No folders configured</info>");
			}
			return 0;
		}

		if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
			foreach ($folders as &$folder) {
				$folder['group_details'] = $folder['groups'];
				$folder['groups'] = array_map(function (array $group) {
					return $group['permissions'];
				}, $folder['groups']);
			}
			$this->writeArrayInOutputFormat($input, $output, $folders);
		} else {
			$table = new Table($output);
			$table->setHeaders(['Folder Id', 'Name', 'Groups', 'Quota', 'Size', 'Advanced Permissions', 'Manage advanced permissions']);
			$table->setRows(array_map(function (array $folder) use ($groupNames): array {
				$folder['size'] = \OCP\Util::humanFileSize($folder['size']);
				$folder['quota'] = ($folder['quota'] > 0) ? \OCP\Util::humanFileSize($folder['quota']) : 'Unlimited';
				$groupStrings = array_map(function (string $groupId, array $entry) use ($groupNames): string {
					[$permissions, $displayName] = [$entry['permissions'], $entry['displayName']];
					$groupName = array_key_exists($groupId, $groupNames) && ($groupNames[$groupId] !== $groupId) ? $groupNames[$groupId] . ' (' . $groupId . ')' : $displayName;
					return $groupName . ': ' . $this->permissionsToString($permissions);
				}, array_keys($folder['groups']), array_values($folder['groups']));
				$folder['groups'] = implode("\n", $groupStrings);
				$folder['acl'] = $folder['acl'] ? 'Enabled' : 'Disabled';
				$manageStrings = array_map(function ($manage) {
					return $manage['displayname'] . ' (' . $manage['type'] . ')';
				}, $folder['manage']);
				$folder['manage'] = implode("\n", $manageStrings);
				return $folder;
			}, $folders));
			$table->render();
		}
		return 0;
	}

	private function permissionsToString(int $permissions): string {
		if ($permissions === 0) {
			return 'none';
		}
		return implode(', ', array_filter(self::PERMISSION_NAMES, function (int $possiblePermission) use ($permissions) {
			return $possiblePermission & $permissions;
		}, ARRAY_FILTER_USE_KEY));
	}
}
