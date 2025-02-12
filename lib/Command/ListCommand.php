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


	public function __construct(
		private FolderManager $folderManager,
		private IRootFolder $rootFolder,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('groupfolders:list')
			->setDescription('List the configured Team folders')
			->addOption('user', 'u', InputArgument::OPTIONAL, 'List Team folders applicable for a user');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getOption('user');
		$groups = $this->groupManager->search('');
		$groupNames = [];
		foreach ($groups as $group) {
			$groupNames[$group->getGID()] = $group->getDisplayName();
		}

		$rootStorageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($rootStorageId === null) {
			$output->writeln('<error>Root storage id not found</error>');
			return 1;
		}

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

		usort($folders, fn (array $a, array $b): int => $a['id'] - $b['id']);

		$outputType = $input->getOption('output');
		if (count($folders) === 0) {
			if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
				$output->writeln('[]');
			} else {
				$output->writeln('<info>No folders configured</info>');
			}

			return 0;
		}

		if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
			foreach ($folders as &$folder) {
				$folder['group_details'] = $folder['groups'];
				$folder['groups'] = array_map(fn (array $group): int => $group['permissions'], $folder['groups']);
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
				$manageStrings = array_map(fn (array $manage): string => $manage['displayname'] . ' (' . $manage['type'] . ')', $folder['manage']);
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

		return implode(', ', array_filter(self::PERMISSION_NAMES, fn (int $possiblePermission): int => $possiblePermission & $permissions, ARRAY_FILTER_USE_KEY));
	}
}
