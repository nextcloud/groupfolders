<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Robin Appelman <robin@icewind.nl>
 * SPDX-FileCopyrightText: 2022 Carl Schwan <carl@carlschwan.eu>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;

class ExpireGroupPlaceholder extends TimedJob {
	public function __construct(ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		// Run at some point in a far far future :p
		$this->setInterval(60 * 60 * 99999999);
	}

	protected function run($argument) {
		// noop
	}
}
