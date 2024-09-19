<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\BackgroundJob;

use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\Trash\TrashBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;

class ExpireGroupTrash extends TimedJob {
	public function __construct(
		private TrashBackend $trashBackend,
		private Expiration $expiration,
		private IAppConfig $config,
		ITimeFactory $timeFactory,
	) {
		parent::__construct($timeFactory);
		// Run once per hour
		$this->setInterval(60 * 60);
	}

	protected function run(mixed $argument): void {
		$backgroundJob = $this->config->getValueString('files_trashbin', 'background_job_expire_trash', 'yes');
		if ($backgroundJob === 'no') {
			return;
		}

		$this->trashBackend->expire($this->expiration);
	}
}
