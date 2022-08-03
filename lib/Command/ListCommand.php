<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {
	public const PERMISSION_NAMES = [
		Constants::PERMISSION_READ => 'read',
		Constants::PERMISSION_UPDATE => 'write',
		Constants::PERMISSION_SHARE => 'share',
		Constants::PERMISSION_DELETE => 'delete'
	];

	private FolderManager $folderManager;
	private IRootFolder $rootFolder;
	private IGroupManager $groupManager;

	public function __construct(FolderManager $folderManager, IRootFolder $rootFolder, IGroupManager $groupManager) {
		parent::__construct();
		$this->folderManager = $folderManager;
		$this->rootFolder = $rootFolder;
		$this->groupManager = $groupManager;
	}

	protected function configure() {
		$this
			->setName('groupfolders:list')
			->setDescription('List the configured group folders');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$groups = $this->groupManager->search('');
		$groupNames = [];
		foreach ($groups as $group) {
			$groupNames[$group->getGID()] = $group->getDisplayName();
		}
		$folders = $this->folderManager->getAllFoldersWithSize($this->rootFolder->getMountPoint()->getNumericStorageId());
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
			$this->writeArrayInOutputFormat($input, $output, $folders);
		} else {
			$table = new Table($output);
			$table->setHeaders(['Folder Id', 'Name', 'Groups', 'Quota', 'Size', 'Advanced Permissions', 'Manage advanced permissions']);
			$table->setRows(array_map(function (array $folder) use ($groupNames): array {
				$folder['size'] = \OCP\Util::humanFileSize($folder['size']);
				$folder['quota'] = ($folder['quota'] > 0) ? \OCP\Util::humanFileSize($folder['quota']) : 'Unlimited';
				$groupStrings = array_map(function (string $groupId, int $permissions) use ($groupNames): string {
					$groupName = array_key_exists($groupId, $groupNames) && ($groupNames[$groupId] !== $groupId) ? $groupNames[$groupId] . ' (' . $groupId . ')' : $groupId;
					return $groupName . ': ' . $this->permissionsToString($permissions);
				}, array_keys($folder['groups']), array_values($folder['groups']));
				$folder['groups'] = implode("\n", $groupStrings);
				$folder['acl'] = $folder['acl'] ? 'Enabled' : 'Disabled';
				$manageStrings = array_map(function ($manage) {
					return $manage['id'] . ' (' . $manage['type'] . ')';
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
		return implode(', ', array_filter(self::PERMISSION_NAMES, function ($possiblePermission) use ($permissions) {
			return $possiblePermission & $permissions;
		}, ARRAY_FILTER_USE_KEY));
	}
}
