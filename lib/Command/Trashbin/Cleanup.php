<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\GroupFolders\Command\Trashbin;

use OC\Core\Command\Base;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Cleanup extends Base {

	/**
	 * @var TrashBackend
	 */
	private $trashBackend;
	/**
	 * @var FolderManager
	 */
	private $folderManager;

	public function __construct(FolderManager $folderManager, IRootFolder $rootFolder) {
		parent::__construct();
		if (\OC::$server->getAppManager()->isEnabledForUser('files_trashbin')) {
			$this->trashBackend = \OC::$server->query(TrashBackend::class);
			$this->folderManager = $folderManager;
		}
	}

	protected function configure() {
		$this
			->setName('groupfolders:trashbin:cleanup')
			->setDescription('Empty the groupfolder trashbin')
			->addArgument('folder_id', InputArgument::OPTIONAL, 'Id of the groupfolder')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (!$this->trashBackend) {
			$output->writeln('<error>files_trashbin is disabled: group folders trashbin is not available</error>');
			return -1;
		}
		$helper = $this->getHelper('question');

		$folders = $this->folderManager->getAllFolders();
		if ($input->getArgument('folder_id') !== null) {
			$folderId = (int)$input->getArgument('folder_id');

			foreach ($folders as $folder) {
				if ($folder['id'] === $folderId) {

					$question = new ConfirmationQuestion('Are you sure you want to empty the trashbin of your group folder with id ' . $folderId . ', this can not be undone (y/N).', false);
					if (!$input->getOption('force') && !$helper->ask($input, $output, $question)) {
						return -1;
					}

					$this->trashBackend->cleanTrashFolder($folder['id']);
					return 0;
				}
			}

			$output->writeln('<error>Folder not found: ' . $folderId . '</error>');
			return -1;
		} else {

			$question = new ConfirmationQuestion('Are you sure you want to empty the trashbin of your group folders, this can not be undone (y/N).', false);
			if (!$input->getOption('force') && !$helper->ask($input, $output, $question)) {
				return -1;
			}

			foreach ($folders as $folder) {
				$this->trashBackend->cleanTrashFolder($folder['id']);
			}
		}

		return 0;
	}
}
