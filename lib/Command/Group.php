<?php declare(strict_types=1);
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
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Group extends FolderCommand {
	const PERMISSION_VALUES = [
		'read' => Constants::PERMISSION_READ,
		'write' => Constants::PERMISSION_UPDATE | Constants::PERMISSION_CREATE,
		'share' => Constants::PERMISSION_SHARE,
		'delete' => Constants::PERMISSION_DELETE,
	];
	/** @var IGroupManager $groupManager */
	private $groupManager;

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

	private function getNewPermissions(array $input) {
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
