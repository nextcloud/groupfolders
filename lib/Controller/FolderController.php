<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\Controller;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FolderController extends Controller {
	/** @var FolderManager */
	private $manager;
	/** @var MountProvider */
	private $mountProvider;

	/**
	 * @param string $AppName
	 * @param IRequest $request
	 * @param FolderManager $manager
	 * @param MountProvider $mountProvider
	 */
	public function __construct($AppName,
								IRequest $request,
								FolderManager $manager,
								MountProvider $mountProvider
	) {
		parent::__construct($AppName, $request);
		$this->manager = $manager;
		$this->mountProvider = $mountProvider;
	}

	public function getFolders() {
		return $this->manager->getAllFolders();
	}

	/**
	 * @param string $mountpoint
	 */
	public function addFolder($mountpoint) {
		$id = $this->manager->createFolder($mountpoint);
		return new JSONResponse(['id' => $id]);
	}

	/**
	 * @param int $id
	 */
	public function removeFolder($id) {
		$folder = $this->mountProvider->getFolder($id);
		if ($folder) {
			$folder->delete();
		}
		$this->manager->removeFolder($id);
	}

	/**
	 * @param int $id
	 * @param string $mountPoint
	 */
	public function setMountPoint($id, $mountPoint) {
		$this->manager->setMountPoint($id, $mountPoint);
	}

	/**
	 * @param int $id
	 * @param string $group
	 */
	public function addGroup($id, $group) {
		$this->manager->addApplicableGroup($id, $group);
	}

	/**
	 * @param int $id
	 * @param string $group
	 */
	public function removeGroup($id, $group) {
		$this->manager->removeApplicableGroup($id, $group);
	}

	/**
	 * @param int $id
	 * @param string $group
	 * @param string $permissions
	 */
	public function setPermissions($id, $group, $permissions) {
		$this->manager->setGroupPermissions($id, $group, $permissions);
	}

	/**
	 * @param int $id
	 * @param int $quota
	 */
	public function setQuota($id, $quota) {
		$this->manager->setFolderQuota($id, $quota);
	}
}
