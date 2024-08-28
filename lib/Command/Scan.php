<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
				InputOption::VALUE_REQUIRED,
				'Limit rescan to this path, eg. --path="/shared/media/Music".'
			)->addOption(
				'shallow',
				null,
				InputOption::VALUE_NONE,
				'Do not scan folders recursively.'
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
