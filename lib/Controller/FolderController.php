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

use OC\AppFramework\OCS\V1Response;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUser;

class FolderController extends OCSController {
	/** @var FolderManager */
	private $manager;
	/** @var MountProvider */
	private $mountProvider;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IUser */
	private $user;

	public function __construct(
		$AppName,
		IRequest $request,
		FolderManager $manager,
		MountProvider $mountProvider,
		IRootFolder $rootFolder,
		IUserSession $userSession
	) {
		parent::__construct($AppName, $request);
		$this->manager = $manager;
		$this->mountProvider = $mountProvider;
		$this->rootFolder = $rootFolder;
		$this->user = $userSession->getUser();

		$this->registerResponder('xml', function ($data) {
			return $this->buildOCSResponseXML('xml', $data);
		});
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 */
	public function getFolders(): DataResponse {
		return new DataResponse($this->manager->getAllFoldersWithSize($this->getRootFolderStorageId()));
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @return DataResponse
	 */
	public function getFolder(int $id): DataResponse {
		return new DataResponse($this->manager->getFolder($id, $this->getRootFolderStorageId()));
	}

	private function getRootFolderStorageId(): int {
		return $this->rootFolder->getMountPoint()->getNumericStorageId();
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param string $mountpoint
	 * @return DataResponse
	 */
	public function addFolder(string $mountpoint): DataResponse {
		$id = $this->manager->createFolder($mountpoint);
		return new DataResponse(['id' => $id]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @return DataResponse
	 */
	public function removeFolder(int $id): DataResponse {
		$folder = $this->mountProvider->getFolder($id);
		if ($folder) {
			$folder->delete();
		}
		$this->manager->removeFolder($id);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param string $mountPoint
	 * @return DataResponse
	 */
	public function setMountPoint(int $id, string $mountPoint): DataResponse {
		$this->manager->setMountPoint($id, $mountPoint);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param string $group
	 * @return DataResponse
	 */
	public function addGroup(int $id, string $group): DataResponse {
		$this->manager->addApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param string $group
	 * @return DataResponse
	 */
	public function removeGroup(int $id, string $group): DataResponse {
		$this->manager->removeApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param string $group
	 * @param int $permissions
	 * @return DataResponse
	 */
	public function setPermissions(int $id, string $group, int $permissions): DataResponse {
		$this->manager->setGroupPermissions($id, $group, $permissions);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param string $mappingType
	 * @param string $mappingId
	 * @param bool $manageAcl
	 * @return DataResponse
	 * @throws \OCP\DB\Exception
	 */
	public function setManageACL(int $id, string $mappingType, string $mappingId, bool $manageAcl): DataResponse {
		$this->manager->setManageACL($id, $mappingType, $mappingId, $manageAcl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param int $quota
	 * @return DataResponse
	 */
	public function setQuota(int $id, int $quota): DataResponse {
		$this->manager->setFolderQuota($id, $quota);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param bool $acl
	 * @return DataResponse
	 */
	public function setACL(int $id, bool $acl): DataResponse {
		$this->manager->setFolderACL($id, $acl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @param int $id
	 * @param string $mountpoint
	 * @return DataResponse
	 */
	public function renameFolder(int $id, string $mountpoint): DataResponse {
		$this->manager->renameFolder($id, $mountpoint);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Overwrite response builder to customize xml handling to deal with spaces in folder names
	 *
	 * @param string $format json or xml
	 * @param DataResponse $data the data which should be transformed
	 * @since 8.1.0
	 * @return \OC\AppFramework\OCS\V1Response
	 */
	private function buildOCSResponseXML(string $format, DataResponse $data): V1Response {
		/** @var array $folderData */
		$folderData = $data->getData();
		if (isset($folderData['id'])) {
			// single folder response
			$folderData = $this->folderDataForXML($folderData);
		} elseif (is_array($folderData) && count($folderData) && isset(current($folderData)['id'])) {
			// folder list
			$folderData = array_map([$this, 'folderDataForXML'], $folderData);
		}
		$data->setData($folderData);
		return new V1Response($data, $format);
	}

	private function folderDataForXML(array $data): array {
		$groups = $data['groups'];
		$data['groups'] = [];
		foreach ($groups as $id => $permissions) {
			$data['groups'][] = ['@group_id' => $id, '@permissions' => $permissions];
		}
		return $data;
	}

	/**
	 * @NoAdminRequired
	 * @param int $id
	 * @param $fileId
	 * @param string $search
	 * @return DataResponse
	 */
	public function aclMappingSearch(int $id, $fileId, string $search = ''): DataResponse {
		$users = [];
		$groups = [];

		if ($this->manager->canManageACL($id, $this->user) === true) {
			$groups = $this->manager->searchGroups($id, $search);
			$users = $this->manager->searchUsers($id, $search);
		}
		return new DataResponse([
			'users' => $users,
			'groups' => $groups,
		]);
	}
}
