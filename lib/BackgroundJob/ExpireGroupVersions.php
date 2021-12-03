<?php declare(strict_types=1);
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

namespace OCA\GroupFolders\BackgroundJob;

use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use OCA\Files_Trashbin\Expiration;
use OCP\IConfig;

class ExpireGroupVersions extends \OC\BackgroundJob\TimedJob {
	const ITEMS_PER_SESSION = 1000;

	/** @var GroupVersionsExpireManager */
	private $expireManager;

	/** @var TrashBackend */
	private $trashBackend;

	/** @var Expiration */
	private $expiration;

	/** @var IConfig */
	private $config;

	public function __construct(
		GroupVersionsExpireManager $expireManager,
		TrashBackend $trashBackend,
		Expiration $expiration,
		IConfig $config
	) {
		// Run once per hour
		$this->setInterval(60 * 60);

		$this->expireManager = $expireManager;
		$this->trashBackend = $trashBackend;
		$this->expiration = $expiration;
		$this->config = $config;
	}

	protected function run($argument) {
		$this->expireManager->expireAll();
		$backgroundJob = $this->config->getAppValue('files_trashbin', 'background_job_expire_trash', 'yes');
		if ($backgroundJob === 'no') {
			return;
		}
		$this->trashBackend->expire($this->expiration);
	}
}
