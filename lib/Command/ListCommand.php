<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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
