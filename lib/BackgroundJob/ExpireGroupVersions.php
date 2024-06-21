<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
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

namespace OCA\GroupFolders\BackgroundJob;

use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class ExpireGroupVersions extends TimedJob {
	private GroupVersionsExpireManager $expireManager;

	public function __construct(GroupVersionsExpireManager $expireManager, ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);

		// Run once per hour
		$this->setInterval(60 * 60);
		// But don't run if still running
		$this->setAllowParallelRuns(false);

		$this->expireManager = $expireManager;
	}

	protected function run($argument) {
		$this->expireManager->expireAll();
	}
}
