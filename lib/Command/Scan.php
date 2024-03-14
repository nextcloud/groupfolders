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

use OC\Files\ObjectStore\ObjectStoreScanner;
use OCP\Constants;
use OCP\Files\Cache\IScanner;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Scan extends FolderCommand {
	protected function configure() {
		$this
			->setName('groupfolders:scan')
			->setDescription('Scan a group folder for outside changes')
			->addArgument(
				'folder_id',
				InputArgument::OPTIONAL,
				'Id of the group folder to scan.'
			)->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'Scan all the group folders.'
			)
			->addOption(
				'path',
				'p',
				InputArgument::OPTIONAL,
				'limit rescan to this path, eg. --path="/shared/media/Music"'
			)->addOption(
				'shallow',
				null,
				InputOption::VALUE_NONE,
				'do not scan folders recursively'
			);
		parent::configure();
	}

	/** @psalm-suppress UndefinedInterfaceMethod setUseTransactions is defined in private class */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$folderId = $input->getArgument('folder_id');
		$all = $input->getOption('all');
		if ($folderId === null && !$all) {
			$output->writeln("Either a group folder id or --all needs to be provided");
			return -1;
		}

		if ($folderId !== null && $all) {
			$output->writeln("Specifying a group folder id and --all are mutually exclusive");
			return -1;
		}

		if ($all) {
			$folders = $this->folderManager->getAllFolders();
		} else {
			$folder = $this->getFolder($input, $output);
			if ($folder === false) {
				return -1;
			}
			$folders = [$folder['id'] => $folder];
		}

		$inputPath = $input->getOption('path');
		if ($inputPath) {
			$inputPath = '/' . trim($inputPath, '/');
		} else {
			$inputPath = '';
		}
		$recursive = !$input->getOption('shallow');

		$duration = 0;
		$stats = [];
		foreach ($folders as $folder) {
			$folderId = $folder['id'];
			$statsRow = [$folderId, 0, 0, 0];
			$mount = $this->mountProvider->getMount($folder['id'], '/' . $folder['mount_point'], Constants::PERMISSION_ALL, $folder['quota']);
			/** @var IScanner&\OC\Hooks\BasicEmitter $scanner */
			$scanner = $mount->getStorage()->getScanner();

			$output->writeln("Scanning group folder with id\t<info>{$folder['id']}</info>", OutputInterface::VERBOSITY_VERBOSE);
			if ($scanner instanceof ObjectStoreScanner) {
				$output->writeln("Scanning group folders using an object store as primary storage is not supported.");
				return -1;
			}

			$scanner->listen('\OC\Files\Cache\Scanner', 'scanFile', function ($path) use ($output, &$statsRow) {
				$output->writeln("\tFile\t<info>/$path</info>", OutputInterface::VERBOSITY_VERBOSE);
				$statsRow[2]++;
				// abortIfInterrupted doesn't exist in nc14
				if (method_exists($this, 'abortIfInterrupted')) {
					$this->abortIfInterrupted();
				}
			});

			$scanner->listen('\OC\Files\Cache\Scanner', 'scanFolder', function ($path) use ($output, &$statsRow) {
				$output->writeln("\tFolder\t<info>/$path</info>", OutputInterface::VERBOSITY_VERBOSE);
				$statsRow[1]++;
				// abortIfInterrupted doesn't exist in nc14
				if (method_exists($this, 'abortIfInterrupted')) {
					$this->abortIfInterrupted();
				}
			});

			$start = microtime(true);

			$scanner->setUseTransactions(false);
			$scanner->scan($inputPath, $recursive);

			$end = microtime(true);
			$statsRow[3] = date('H:i:s', (int)($end - $start));
			$output->writeln("", OutputInterface::VERBOSITY_VERBOSE);
			$stats[] = $statsRow;
		}

		$headers = [
			'Folder Id', 'Folders', 'Files', 'Elapsed time'
		];

		$this->showSummary($headers, $stats, $output, $duration);
		return 0;
	}

	protected function showSummary($headers, $rows, OutputInterface $output, float $duration): void {
		$table = new Table($output);
		$table
			->setHeaders($headers)
			->setRows($rows);
		$table->render();
	}
}
