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

namespace OCA\GroupFolders\Versions;


use OC\Files\FileInfo;
use OC\Files\View;
use OC\Hooks\BasicEmitter;
use OC\User\User;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\AppFramework\Utility\ITimeFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GroupVersionsExpireManager extends BasicEmitter {
	private $folderManager;
	private $expireManager;
	private $versionsBackend;
	private $timeFactory;
	private $dispatcher;

	public function __construct(
		FolderManager $folderManager,
		ExpireManager $expireManager,
		VersionsBackend $versionsBackend,
		ITimeFactory $timeFactory,
		EventDispatcherInterface $dispatcher
	) {
		$this->folderManager = $folderManager;
		$this->expireManager = $expireManager;
		$this->versionsBackend = $versionsBackend;
		$this->timeFactory = $timeFactory;
		$this->dispatcher = $dispatcher;
	}

	public function expireAll() {
		$folders = $this->folderManager->getAllFolders();
		foreach ($folders as $folder) {
			$this->emit(self::class, 'enterFolder', [$folder]);
			$this->expireFolder($folder);
		}
	}

	public function expireFolder($folder) {
		$view = new View('/__groupfolders/versions/' . $folder['id']);
		$files = $this->versionsBackend->getAllVersionedFiles($folder);
		$dummyUser = new User('', null, $this->dispatcher);
		foreach ($files as $fileId => $file) {
			if ($file instanceof FileInfo) {
				$versions = $this->versionsBackend->getVersionsForFile($dummyUser, $file);
				$expireVersions = $this->expireManager->getExpiredVersion($versions, $this->timeFactory->getTime(), false);
				foreach ($expireVersions as $version) {
					/** @var GroupVersion $version */
					$this->emit(self::class, 'deleteVersion', [$version]);
					$view->unlink('/' . $fileId . '/' . $version->getVersionFile()->getName());
				}
			} else {
				// source file no longer exists
				$this->emit(self::class, 'deleteFile', [$fileId]);
				$this->versionsBackend->deleteAllVersionsForFile($folder['id'], $fileId);
			}
		}
	}
}
