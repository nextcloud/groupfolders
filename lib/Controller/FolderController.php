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
use OCA\GroupFolders\Service\DelegationService;
use OCA\GroupFolders\Service\FoldersFilter;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IUser;

class FolderController extends OCSController {
	private FolderManager $manager;
	private MountProvider $mountProvider;
	private IRootFolder $rootFolder;
	private ?IUser $user = null;
	private FoldersFilter $foldersFilter;
	private DelegationService $delegationService;

	public function __construct(
		string $AppName,
		IRequest $request,
		FolderManager $manager,
		MountProvider $mountProvider,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		FoldersFilter $foldersFilter,
		DelegationService $delegationService
	) {
		parent::__construct($AppName, $request);
		$this->foldersFilter = $foldersFilter;
		$this->manager = $manager;
		$this->mountProvider = $mountProvider;
		$this->rootFolder = $rootFolder;
		$this->user = $userSession->getUser();

		$this->registerResponder('xml', function ($data): V1Response {
			return $this->buildOCSResponseXML('xml', $data);
		});
		$this->delegationService = $delegationService;
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function getFolders(): DataResponse {
		$folders = $this->manager->getAllFoldersWithSize($this->getRootFolderStorageId());
		if ($this->delegationService->isAdminNextcloud() || $this->delegationService->isDelegatedAdmin()) {
			return new DataResponse($folders);
		}
		if ($this->delegationService->hasOnlyApiAccess()) {
			$folders = $this->foldersFilter->getForApiUser($folders);
		}
		return new DataResponse($folders);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function getFolder(int $id): DataResponse {
		return new DataResponse($this->manager->getFolder($id, $this->getRootFolderStorageId()));
	}

	private function getRootFolderStorageId(): int {
		return $this->rootFolder->getMountPoint()->getNumericStorageId();
	}

	/**
	 * @RequireGroupFolderAdmin
	 * @NoAdminRequired
	 */
	public function addFolder(string $mountpoint): DataResponse {
		$id = $this->manager->createFolder($mountpoint);
		return new DataResponse(['id' => $id]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
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
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function setMountPoint(int $id, string $mountPoint): DataResponse {
		$this->manager->setMountPoint($id, $mountPoint);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function addGroup(int $id, string $group): DataResponse {
		$this->manager->addApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function removeGroup(int $id, string $group): DataResponse {
		$this->manager->removeApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function setPermissions(int $id, string $group, int $permissions): DataResponse {
		$this->manager->setGroupPermissions($id, $group, $permissions);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @throws \OCP\DB\Exception
	 */
	public function setManageACL(int $id, string $mappingType, string $mappingId, bool $manageAcl): DataResponse {
		$this->manager->setManageACL($id, $mappingType, $mappingId, $manageAcl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function setQuota(int $id, int $quota): DataResponse {
		$this->manager->setFolderQuota($id, $quota);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function setACL(int $id, bool $acl): DataResponse {
		$this->manager->setFolderACL($id, $acl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
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
	 */
	public function aclMappingSearch(int $id, ?int $fileId, string $search = ''): DataResponse {
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
