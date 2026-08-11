<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

class ExpireGroupTrash extends ExpireGroupBase {
	public function __construct(
		private readonly TrashBackend $trashBackend,
		private readonly Expiration $expiration,
	) {
	}

	#[\Override]
	public function __invoke(IOutput $output): ExitCode {
		[$count, $size] = $this->trashBackend->expire($this->expiration);
		$output->writeln("<info>Removed $count expired trashbin items</info>");

		return ExitCode::Success;
	}
}
