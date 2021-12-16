<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
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
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for commands asking the user for a folder id.
 */
abstract class FolderCommand extends Base {
	/** @var FolderManager $folderManager */
	protected $folderManager;
	/** @var IRootFolder $rootFolder */
	protected $rootFolder;
	/** @var MountProvider $mountProvider */
	protected $mountProvider;

	public function __construct(FolderManager $folderManager, IRootFolder $rootFolder, MountProvider $mountProvider) {
		parent::__construct();
		$this->folderManager = $folderManager;
		$this->rootFolder = $rootFolder;
		$this->mountProvider = $mountProvider;
	}

	/**
	 * @psalm-return array{id: mixed, mount_point: mixed, groups: array<empty, empty>|mixed, quota: int, size: int|mixed, acl: bool}|false
	 */
	protected function getFolder(InputInterface $input, OutputInterface $output) {
		$folderId = (int)$input->getArgument('folder_id');
		if ((string)$folderId !== $input->getArgument('folder_id')) {
			// Protect against removing folderId === 0 when typing a string (e.g. folder name instead of folder id)
			$output->writeln('<error>Folder id argument is not an integer. Got ' . $input->getArgument('folder_id') . '</error>');
			return false;
		}
		$folder = $this->folderManager->getFolder($folderId, $this->rootFolder->getMountPoint()->getNumericStorageId());
		if ($folder === false) {
			$output->writeln('<error>Folder not found: ' . $folderId . '</error>');
			return false;
		}
		return $folder;
	}
}
