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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class FolderController extends OCSController {
	private FolderManager $manager;
	private MountProvider $mountProvider;
	private IRootFolder $rootFolder;
	private ?IUser $user = null;
	private FoldersFilter $foldersFilter;
	private DelegationService $delegationService;
	private IGroupManager $groupManager;

	public function __construct(
		string $AppName,
		IRequest $request,
		FolderManager $manager,
		MountProvider $mountProvider,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		FoldersFilter $foldersFilter,
		DelegationService $delegationService,
		IGroupManager $groupManager,
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
		$this->groupManager = $groupManager;
	}

	/**
	 * Regular users can access their own folders, but they only get to see the permission for their own groups
	 *
	 * @param array $folder
	 * @return array|null
	 */
	private function filterNonAdminFolder(array $folder): ?array {
		$userGroups = $this->groupManager->getUserGroupIds($this->user);
		$folder['groups'] = array_filter($folder['groups'], function (string $group) use ($userGroups) {
			return in_array($group, $userGroups);
		}, ARRAY_FILTER_USE_KEY);
		if ($folder['groups']) {
			return $folder;
		} else {
			return null;
		}
	}

	/**
	 * @param array{id: mixed, mount_point: mixed, groups: array<string, array{displayName: string, type: string, permissions: integer}>, quota: int, size: int, acl: bool} $folder
	 * @return array{id: mixed, mount_point: mixed, groups:array<string, integer>, group_details: array<empty, empty>|mixed, quota: int, size: int, acl: bool}
	 */
	private function formatFolder(array $folder): array {
		// keep compatibility with the old 'groups' field
		$folder['group_details'] = $folder['groups'];
		$folder['groups'] = array_map(function (array $group) {
			return $group['permissions'];
		}, $folder['groups']);
		return $folder;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getFolders(bool $applicable = false): DataResponse {
		$folders = $this->manager->getAllFoldersWithSize($this->getRootFolderStorageId());
		$folders = array_map([$this, 'formatFolder'], $folders);
		$isAdmin = $this->delegationService->isAdminNextcloud() || $this->delegationService->isDelegatedAdmin();
		if ($isAdmin && !$applicable) {
			return new DataResponse($folders);
		}
		if ($this->delegationService->hasOnlyApiAccess()) {
			$folders = $this->foldersFilter->getForApiUser($folders);
		}
		if ($applicable || !$this->delegationService->hasApiAccess()) {
			$folders = array_map([$this, 'filterNonAdminFolder'], $folders);
			$folders = array_filter($folders);
		}
		return new DataResponse($folders);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getFolder(int $id): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}

		$storageId = $this->getRootFolderStorageId();
		$folder = $this->manager->getFolder($id, $storageId);
		if (!$this->delegationService->hasApiAccess()) {
			$folder = $this->filterNonAdminFolder($folder);
			if (!$folder) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}
		return new DataResponse($this->formatFolder($folder));
	}

	private function checkFolderExists(int $id): ?DataResponse {
		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$folder = $this->manager->getFolder($id, $storageId);
		if ($folder === false) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		return null;
	}

	private function getRootFolderStorageId(): ?int {
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
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$folder = $this->mountProvider->getFolder($id);
		$folder->delete();
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
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->addApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function removeGroup(int $id, string $group): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->removeApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function setPermissions(int $id, string $group, int $permissions): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setGroupPermissions($id, $group, $permissions);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @throws \OCP\DB\Exception
	 */
	public function setManageACL(int $id, string $mappingType, string $mappingId, bool $manageAcl): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setManageACL($id, $mappingType, $mappingId, $manageAcl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function setQuota(int $id, int $quota): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setFolderQuota($id, $quota);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function setACL(int $id, bool $acl): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setFolderACL($id, $acl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 */
	public function renameFolder(int $id, string $mountpoint): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->renameFolder($id, $mountpoint);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Overwrite response builder to customize xml handling to deal with spaces in folder names
	 *
	 * @param string $format json or xml
	 * @param DataResponse $data the data which should be transformed
	 * @return \OC\AppFramework\OCS\V1Response
	 * @since 8.1.0
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
		$groups = $data['group_details'];
		$data['groups'] = [];
		unset($data['group_details']);
		foreach ($groups as $id => $group) {
			$data['groups'][] = [
				'@group_id' => $id,
				'@permissions' => $group['permissions'],
				'@display-name' => $group['displayName'],
				'@type' => $group['type'],
			];
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
