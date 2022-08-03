<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 * SPDX-FileCopyrightText: 2021 Carl Schwan <carl@carlschwan.eu>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\BackgroundJob;

use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;

class ExpireGroupVersions extends TimedJob {
	private GroupVersionsExpireManager $expireManager;

	public function __construct(GroupVersionsExpireManager $expireManager, ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		// Run once per hour
		$this->setInterval(60 * 60);

		$this->expireManager = $expireManager;
	}

	protected function run($argument) {
		$this->expireManager->expireAll();
	}
}
