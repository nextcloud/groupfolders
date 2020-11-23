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
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Rename extends Base {
	private $folderManager;
	private $rootFolder;

	public function __construct(FolderManager $folderManager, IRootFolder $rootFolder) {
		parent::__construct();
		$this->folderManager = $folderManager;
		$this->rootFolder = $rootFolder;
	}

	protected function configure() {
		$this
			->setName('groupfolders:rename')
			->setDescription('Rename group folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to rename')
			->addArgument('name', InputArgument::REQUIRED, 'New value name of the folder');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$folderId = $input->getArgument('folder_id');
		$folder = $this->folderManager->getFolder($folderId, $this->rootFolder->getMountPoint()->getNumericStorageId());
		if ($folder) {
			$this->folderManager->renameFolder($folderId, $input->getArgument('name'));
			return 0;
		} else {
			$output->writeln('<error>Folder not found: ' . $folderId . '</error>');
			return -1;
		}
	}
}
