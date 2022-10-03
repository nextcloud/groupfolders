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

use OC\Files\ObjectStore\NoopScanner;
use OCP\Constants;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OCP\Files\Cache\IScanner;

class Scan extends FolderCommand {
	private int $foldersCounter = 0;
	private int $filesCounter = 0;

	protected function configure() {
		$this
			->setName('groupfolders:scan')
			->setDescription('Scan a group folder for outside changes')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to configure');
		parent::configure();
	}

	/** @psalm-suppress UndefinedInterfaceMethod setUseTransactions is defined in private class */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$folder = $this->getFolder($input, $output);
		if ($folder === false) {
			return -1;
		}
		$mount = $this->mountProvider->getMount($folder['id'], '/' . $folder['mount_point'], Constants::PERMISSION_ALL, $folder['quota']);
		/** @var IScanner&\OC\Hooks\BasicEmitter $scanner */
		$scanner = $mount->getStorage()->getScanner();

		if ($scanner instanceof NoopScanner) {
			$output->writeln("Scanning group folders using an object store as primary storage is not supported.");
			return -1;
		}

		$scanner->listen('\OC\Files\Cache\Scanner', 'scanFile', function ($path) use ($output) {
			$output->writeln("\tFile\t<info>$path</info>", OutputInterface::VERBOSITY_VERBOSE);
			++$this->filesCounter;
			// abortIfInterrupted doesn't exist in nc14
			if (method_exists($this, 'abortIfInterrupted')) {
				$this->abortIfInterrupted();
			}
		});

		$scanner->listen('\OC\Files\Cache\Scanner', 'scanFolder', function ($path) use ($output) {
			$output->writeln("\tFolder\t<info>$path</info>", OutputInterface::VERBOSITY_VERBOSE);
			++$this->foldersCounter;
			// abortIfInterrupted doesn't exist in nc14
			if (method_exists($this, 'abortIfInterrupted')) {
				$this->abortIfInterrupted();
			}
		});

		$start = microtime(true);

		$scanner->setUseTransactions(false);
		$scanner->scan('');

		$end = microtime(true);

		$headers = [
			'Folders', 'Files', 'Elapsed time'
		];

		$this->showSummary($headers, null, $output, $end - $start);
		return 0;
	}

	protected function showSummary($headers, $rows, OutputInterface $output, float $duration): void {
		$niceDate = date('H:i:s', (int)$duration);
		if (!$rows) {
			$rows = [
				$this->foldersCounter,
				$this->filesCounter,
				$niceDate,
			];
		}
		$table = new Table($output);
		$table
			->setHeaders($headers)
			->setRows([$rows]);
		$table->render();
	}
}
