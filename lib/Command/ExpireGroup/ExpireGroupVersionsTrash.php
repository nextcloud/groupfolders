<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;
use OCP\EventDispatcher\IEventDispatcher;

class ExpireGroupVersionsTrash extends ExpireGroupVersions {
	public function __construct(
		GroupVersionsExpireManager $expireManager,
		IEventDispatcher $eventDispatcher,
		private readonly TrashBackend $trashBackend,
		private readonly Expiration $expiration,
	) {
		parent::__construct($expireManager, $eventDispatcher);
	}

	#[\Override]
	public function __invoke(IOutput $output): ExitCode {
		parent::__invoke($output);

		[$count, $size] = $this->trashBackend->expire($this->expiration);
		$output->writeln("<info>Removed $count expired trashbin items</info>");

		return ExitCode::Success;
	}
}
