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
use OCP\IConfig;

class ExpireGroupTrash extends TimedJob {
	private TrashBackend $trashBackend;
	private Expiration $expiration;
	private IConfig $config;

	public function __construct(
		TrashBackend $trashBackend,
		Expiration $expiration,
		IConfig $config,
		ITimeFactory $timeFactory
	) {
		parent::__construct($timeFactory);
		// Run once per hour
		$this->setInterval(60 * 60);

		$this->trashBackend = $trashBackend;
		$this->expiration = $expiration;
		$this->config = $config;
	}

	protected function run($argument) {
		$backgroundJob = $this->config->getAppValue('files_trashbin', 'background_job_expire_trash', 'yes');
		if ($backgroundJob === 'no') {
			return;
		}
		$this->trashBackend->expire($this->expiration);
	}
}
