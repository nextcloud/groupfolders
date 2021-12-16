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
use OCA\GroupFolders\Command\FolderCommand;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Quota extends FolderCommand {
	protected function configure() {
		$this
			->setName('groupfolders:quota')
			->setDescription('Edit the quota of a configured group folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to configure')
			->addArgument('quota', InputArgument::REQUIRED, 'New value for the quota of the folder');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$folder = $this->getFolder($input, $output);
		if ($folder === false) {
			return -1;
		}
		$quotaString = strtolower($input->getArgument('quota'));
		$quota = ($quotaString === 'unlimited') ? FileInfo::SPACE_UNLIMITED : \OCP\Util::computerFileSize($quotaString);
		if ($quota) {
			$this->folderManager->setFolderQuota($folder['id'], $quota);
			return 0;
		}
		$output->writeln('<error>Unable to parse quota input: ' . $quotaString . '</error>');
		return -1;
	}
}
