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
use OCP\AppFramework\Controller;
use OCP\IRequest;

class FolderController extends Controller {
	/** @var FolderManager */
	private $manager;

	/**
	 * @param string $AppName
	 * @param IRequest $request
	 * @param FolderManager $manager
	 */
	public function __construct($AppName,
								IRequest $request,
								FolderManager $manager
	) {
		parent::__construct($AppName, $request);
		$this->manager = $manager;
	}

	public function getFolders() {
		return $this->manager->getAllFolders();
	}

	/**
	 * @param string $mountpoint
	 * @return int
	 */
	public function addFolder($mountpoint) {
		return $this->manager->createFolder($mountpoint);
	}

	/**
	 * @param int $id
	 */
	public function removeFolder($id) {
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
}
