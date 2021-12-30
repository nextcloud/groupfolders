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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Delete extends FolderCommand {
	protected function configure() {
		$this
			->setName('groupfolders:delete')
			->setDescription('Delete group folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to rename')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$folder = $this->getFolder($input, $output);
		if ($folder === false) {
			return -1;
		}
		$helper = $this->getHelper('question');
		$question = new ConfirmationQuestion('Are you sure you want to delete the group folder ' . $folder['mount_point'] . ' and all files within, this cannot be undone (y/N).', false);
		if ($input->getOption('force') || $helper->ask($input, $output, $question)) {
			$folderMount = $this->mountProvider->getFolder($folder['id']);
			$this->folderManager->removeFolder($folder['id']);
			$folderMount->delete();
		}
		return 0;
	}
}
