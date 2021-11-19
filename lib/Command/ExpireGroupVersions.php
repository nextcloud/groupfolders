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

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Versions\Versions\IVersion;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpireGroupVersions extends Base {
	/** @var GroupVersionsExpireManager */
	private $expireManager;

	/** @var TrashBackend */
	private $trashBackend;

	/** @var Expiration */
	private $expiration;

	public function __construct(
		GroupVersionsExpireManager $expireManager,
		TrashBackend $trashBackend,
		Expiration $expiration
	) {
		parent::__construct();
		$this->expireManager = $expireManager;
		$this->trashBackend = $trashBackend;
		$this->expiration = $expiration;
	}

	protected function configure() {
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiry of versions and trashbin for files stored in group folders');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->expireManager->listen(GroupVersionsExpireManager::class, 'enterFolder', function (array $folder) use ($output) {
			$output->writeln("<info>Expiring version in '${folder['mount_point']}'</info>");
		});
		$this->expireManager->listen(GroupVersionsExpireManager::class, 'deleteVersion', function (IVersion $version) use ($output) {
			$id = $version->getRevisionId();
			$file = $version->getSourceFileName();
			$output->writeln("<info>Expiring version $id for '$file'</info>");
		});

		$this->expireManager->listen(GroupVersionsExpireManager::class, 'deleteFile', function ($id) use ($output) {
			$output->writeln("<info>Cleaning up versions for no longer existing file with id $id</info>");
		});


		list($count, $size) = $this->trashBackend->expire($this->expiration);
		$output->writeln("<info>Removed $count expired trashbin items</info>");

		$this->expireManager->expireAll();
		return 0;
	}
}
