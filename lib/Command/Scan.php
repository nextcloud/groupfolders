<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

use OC\Files\Cache\Scanner;
use OC\Files\ObjectStore\ObjectStoreScanner;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\FolderStorageManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorageFactory;
use RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Scan extends FolderCommand {
	public function __construct(
		FolderManager $folderManager,
		IRootFolder $rootFolder,
		MountProvider $mountProvider,
		FolderStorageManager $folderStorageManager,
		private readonly IStorageFactory $storageFactory,
	) {
		parent::__construct($folderManager, $rootFolder, $mountProvider, $folderStorageManager);
	}

	protected function configure(): void {
		$this
			->setName('groupfolders:scan')
			->setDescription('Scan a Team folder for outside changes')
			->addArgument(
				'folder_id',
				InputArgument::OPTIONAL,
				'Id of the Team folder to scan.'
			)->addOption(
				'all',
				null,
				InputOption::VALUE_NONE,
				'Scan all the Team folders.'
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

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$folderId = $input->getArgument('folder_id');
		$all = $input->getOption('all');
		if ($folderId === null && !$all) {
			$output->writeln('Either a Team folder id or --all needs to be provided');
			return -1;
		}

		if ($folderId !== null && $all) {
			$output->writeln('Specifying a Team folder id and --all are mutually exclusive');
			return -1;
		}

		if ($all) {
			$folders = $this->folderManager->getAllFoldersWithSize();
		} else {
			$folder = $this->getFolder($input, $output);
			if ($folder === null) {
				return -1;
			}

			$folders = [$folder->id => $folder];
		}

		$inputPath = $input->getOption('path');
		if ($inputPath !== null) {
			if (!is_string($inputPath)) {
				$output->writeln('<error><path> option has to be a string</error>');
				return -3;
			}

			$inputPath = '/' . trim($inputPath, '/');
		} else {
			$inputPath = '';
		}

		$recursive = !$input->getOption('shallow');

		$stats = [];
		foreach ($folders as $folder) {
			$folderId = $folder->id;
			$folderWithPermissions = FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL);
			if ($inputPath === '') {
				$mounts = [
					'files' => $this->mountProvider->getMount($folderWithPermissions, '/' . $folder->mountPoint),
					'trashbin' => $this->mountProvider->getTrashMount($folderWithPermissions, '/' . $folder->mountPoint, $this->storageFactory, null),
					'version' => $this->mountProvider->getVersionsMount($folderWithPermissions, '/' . $folder->mountPoint, $this->storageFactory)
				];
			} else {
				$mounts = [
					'files' => $this->mountProvider->getMount($folderWithPermissions, '/' . $folder->mountPoint)
				];
			}
			foreach ($mounts as $type => $mount) {
				$statsRow = ["$folderId - $type", 0, 0, 0, 0];
				$storage = $mount->getStorage();
				if ($storage === null) {
					throw new RuntimeException('Failed to get storage for mount.');
				}
				/** @var Scanner&\OC\Hooks\BasicEmitter $scanner */
				$scanner = $storage->getScanner();

				$output->writeln("Scanning Team folder with id\t<info>$folderId - $type</info>", OutputInterface::VERBOSITY_VERBOSE);
				if ($scanner instanceof ObjectStoreScanner) {
					$output->writeln('Scanning Team folders using an object store as primary storage is not supported.');
					return -1;
				}

				$scanner->listen('\OC\Files\Cache\Scanner', 'scanFile', function (string $path) use ($output, &$statsRow): void {
					$output->writeln("\tFile\t<info>/$path</info>", OutputInterface::VERBOSITY_VERBOSE);
					$statsRow[2]++;
					$this->abortIfInterrupted();
				});

				$scanner->listen('\OC\Files\Cache\Scanner', 'scanFolder', function (string $path) use ($output, &$statsRow): void {
					$output->writeln("\tFolder\t<info>/$path</info>", OutputInterface::VERBOSITY_VERBOSE);
					$statsRow[1]++;
					$this->abortIfInterrupted();
				});

				$scanner->listen('\OC\Files\Cache\Scanner', 'normalizedNameMismatch', function (string $fullPath) use ($output, &$statsRow): void {
					$output->writeln("\t<error>Entry \"" . $fullPath . '" will not be accessible due to incompatible encoding</error>');
					$statsRow[3]++;
				});

				$start = microtime(true);

				$scanner->setUseTransactions(false);
				$scanner->scan($inputPath, $recursive);

				$end = microtime(true);
				$statsRow[4] = date('H:i:s', (int)($end - $start));
				$output->writeln('', OutputInterface::VERBOSITY_VERBOSE);
				$stats[] = $statsRow;
			}
		}

		$headers = [
			'Folder Id', 'Folders', 'Files', 'Errors', 'Elapsed time'
		];

		$this->showSummary($headers, $stats, $output);

		return 0;
	}

	/**
	 * @param list<string> $headers
	 * @param list<array{string, int, int, int, string}> $rows
	 */
	protected function showSummary(array $headers, array $rows, OutputInterface $output): void {
		$table = new Table($output);
		$table
			->setHeaders($headers)
			->setRows($rows);
		$table->render();
	}
}
