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
use OCP\Console\Attribute\Argument;
use OCP\Console\Attribute\AsCommand;
use OCP\Console\Attribute\Option;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;
use OCP\Console\ISignalHandler;
use OCP\Console\Verbosity;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorageFactory;
use RuntimeException;

#[AsCommand(
	name: 'groupfolders:scan',
	description: 'Scan a Team folder for outside changes',
	supportsOutputFormat: true,
)]
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

	public function __invoke(
		IOutput $output,
		ISignalHandler $signalHandler,
		#[Argument(name: 'folder_id', description: 'Id of the Team folder to scan.')]
		?string $folderId = null,
		#[Option(description: 'Scan all the Team folders.')]
		bool $all = false,
		#[Option(description: 'Limit rescan to this path, eg. --path="/shared/media/Music".', shortcut: 'p')]
		?string $path = null,
		#[Option(description: 'Do not scan folders recursively.')]
		bool $shallow = false,
	): ExitCode|int {
		if ($folderId !== null && $all) {
			$output->writeln('Specifying a Team folder id and --all are mutually exclusive');
			return -1;
		}

		if ($all) {
			$folders = $this->folderManager->getAllFoldersWithSize();
		} elseif ($folderId !== null) {
			$folder = $this->getFolder($folderId, $output);
			if ($folder === null) {
				return -1;
			}

			$folders = [$folder->id => $folder];
		} else {
			$output->writeln('Either a Team folder id or --all needs to be provided');
			return -1;
		}

		$inputPath = ($path !== null) ? '/' . trim($path, '/') : '';

		$recursive = !$shallow;

		$stats = [];
		foreach ($folders as $folder) {
			$currentFolderId = $folder->id;
			$folderWithPermissions = FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL);
			if ($inputPath === '') {
				$mounts = [
					'files' => $this->mountProvider->getMount($folderWithPermissions, '/' . $folder->mountPoint),
				];

				if (interface_exists(\OCA\Files_Versions\Versions\IVersionBackend::class)) {
					$mounts['version'] = $this->mountProvider->getVersionsMount($folderWithPermissions, '/' . $folder->mountPoint, $this->storageFactory);
				}

				if (interface_exists(\OCA\Files_Trashbin\Trash\ITrashBackend::class)) {
					$mounts['trashbin'] = $this->mountProvider->getTrashMount($folderWithPermissions, '/' . $folder->mountPoint, $this->storageFactory, null);
				}
			} else {
				$mounts = [
					'files' => $this->mountProvider->getMount($folderWithPermissions, '/' . $folder->mountPoint)
				];
			}
			foreach ($mounts as $type => $mount) {
				$statsRow = ["$currentFolderId - $type", 0, 0, 0, 0];
				$storage = $mount->getStorage();
				if ($storage === null) {
					throw new RuntimeException('Failed to get storage for mount.');
				}
				/** @var Scanner&\OC\Hooks\BasicEmitter $scanner */
				$scanner = $storage->getScanner();

				$output->writeln("Scanning Team folder with id\t<info>$currentFolderId - $type</info>", Verbosity::Verbose);
				if ($scanner instanceof ObjectStoreScanner) {
					$output->writeln('Scanning Team folders using an object store as primary storage is not supported.');
					return -1;
				}

				$scanner->listen('\\' . \OC\Files\Cache\Scanner::class, 'scanFile', function (string $path) use ($output, $signalHandler, &$statsRow): void {
					$output->writeln("\tFile\t<info>/$path</info>", Verbosity::Verbose);
					$statsRow[2]++;
					$signalHandler->abortIfInterrupted();
				});

				$scanner->listen('\\' . \OC\Files\Cache\Scanner::class, 'scanFolder', function (string $path) use ($output, $signalHandler, &$statsRow): void {
					$output->writeln("\tFolder\t<info>/$path</info>", Verbosity::Verbose);
					$statsRow[1]++;
					$signalHandler->abortIfInterrupted();
				});

				$scanner->listen('\\' . \OC\Files\Cache\Scanner::class, 'normalizedNameMismatch', function (string $fullPath) use ($output, &$statsRow): void {
					$output->writeln("\t<error>Entry \"" . $fullPath . '" will not be accessible due to incompatible encoding</error>');
					$statsRow[3]++;
				});

				$start = microtime(true);

				$scanner->setUseTransactions(false);
				$scanner->scan($inputPath, $recursive);

				$end = microtime(true);
				$statsRow[4] = date('H:i:s', (int)($end - $start));
				$output->writeln('', Verbosity::Verbose);
				$stats[] = $statsRow;
			}
		}

		$this->showSummary($stats, $output);

		return ExitCode::Success;
	}

	/**
	 * @param list<array{string, int, int, int, string}> $rows
	 */
	protected function showSummary(array $rows, IOutput $output): void {
		$headers = ['Folder Id', 'Folders', 'Files', 'Errors', 'Elapsed time'];
		$output->writeTableInOutputFormat(array_map(fn (array $row): array => array_combine($headers, $row), $rows));
	}
}
