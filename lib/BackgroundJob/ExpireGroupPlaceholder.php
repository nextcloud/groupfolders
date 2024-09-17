<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class ExpireGroupPlaceholder extends TimedJob {
	public function __construct(ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		// Run at some point in a far far future :p
		$this->setInterval(60 * 60 * 99999999);
	}

	protected function run(mixed $argument): void {
		// noop
	}
}
