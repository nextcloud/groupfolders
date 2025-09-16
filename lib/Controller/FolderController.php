<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Controller;

use OC\AppFramework\OCS\V1Response;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\ResponseDefinitions;
use OCA\GroupFolders\Service\DelegationService;
use OCA\GroupFolders\Service\FoldersFilter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

/**
 * @psalm-import-type GroupFoldersGroup from ResponseDefinitions
 * @psalm-import-type GroupFoldersCircle from ResponseDefinitions
 * @psalm-import-type GroupFoldersUser from ResponseDefinitions
 * @psalm-import-type GroupFoldersFolder from ResponseDefinitions
 * @psalm-import-type InternalFolderOut from FolderManager
 */
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
	 * @param GroupFoldersFolder $folder
	 * @return null|GroupFoldersFolder
	 */
	private function filterNonAdminFolder(array $folder): ?array {
		$userGroups = $this->groupManager->getUserGroupIds($this->user);
		$folder['groups'] = array_filter($folder['groups'], static fn (string $group): bool => in_array($group, $userGroups, true), ARRAY_FILTER_USE_KEY);
		$folder['group_details'] = array_filter($folder['group_details'], static fn (string $group): bool => in_array($group, $userGroups, true), ARRAY_FILTER_USE_KEY);
		if ($folder['groups'] !== []) {
			return $folder;
		}

		return null;
	}

	/**
	 * @param InternalFolderOut $folder
	 * @return GroupFoldersFolder
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
	 * Gets all Groupfolders
	 * @NoAdminRequired
	 * @param bool $applicable Filter by applicable groups
	 * @return DataResponse<Http::STATUS_OK, array<string, GroupFoldersFolder>, array{}>
	 * @throws OCSNotFoundException Storage not found
	 *
	 * 200: Groupfolders returned
	 */
	public function getFolders(bool $applicable = false): DataResponse {
		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			throw new OCSNotFoundException();
		}

		$folders = [];
		foreach ($this->manager->getAllFoldersWithSize($storageId) as $id => $folder) {
			// Make them string-indexed for OpenAPI JSON output
			$folders[(string)$id] = $this->formatFolder($folder);
		}
		$isAdmin = $this->delegationService->isAdminNextcloud() || $this->delegationService->isDelegatedAdmin();
		if ($isAdmin && !$applicable) {
			return new DataResponse($folders);
		}
		if ($this->delegationService->hasOnlyApiAccess()) {
			$folders = $this->foldersFilter->getForApiUser($folders);
		}
		if ($applicable || !$this->delegationService->hasApiAccess()) {
			$folders = array_filter(array_map($this->filterNonAdminFolder(...), $folders));
		}
		return new DataResponse($folders);
	}

	/**
	 * Gets a Groupfolder by ID
	 * @NoAdminRequired
	 * @param int $id ID of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, GroupFoldersFolder, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 *
	 * 200: Groupfolder returned
	 */
	public function getFolder(int $id): DataResponse {
		$storageId = $this->getRootFolderStorageId();
		$folder = $this->manager->getFolder($id, $storageId);
		if ($folder === null) {
			throw new OCSNotFoundException();
		}
		$folder = $this->formatFolder($folder);

		if (!$this->delegationService->hasApiAccess()) {
			$folder = $this->filterNonAdminFolder($folder);
			if (!$folder) {
				return new DataResponse([], Http::STATUS_NOT_FOUND);
			}
		}
		return new DataResponse($folder);
	}

	/**
	 * @return DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>|null
	 */
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
	 * Add a new Groupfolder
	 * @RequireGroupFolderAdmin
	 * @NoAdminRequired
	 * @param string $mountpoint Mountpoint of the new Groupfolder
	 * @return DataResponse<Http::STATUS_OK, GroupFoldersFolder, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 *
	 * 200: Groupfolder added successfully
	 */
	#[PasswordConfirmationRequired]
	public function addFolder(string $mountpoint): DataResponse {
		$id = $this->manager->createFolder(trim($mountpoint));
		$folder = $this->manager->getFolder($id, $this->rootFolder->getMountPoint()->getNumericStorageId());
		if ($folder === false) {
			throw new OCSNotFoundException();
		}
		return new DataResponse($this->formatFolder($folder));
	}

	/**
	 * Remove a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 *
	 * 200: Groupfolder removed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
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
	 * Set the mount point of a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param string $mountPoint New mount point path
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>
	 *
	 * 200: Mount point changed successfully
	 */
	#[PasswordConfirmationRequired]
	public function setMountPoint(int $id, string $mountPoint): DataResponse {
		$this->manager->renameFolder($id, trim($mountPoint));
		return new DataResponse(['success' => true]);
	}

	/**
	 * Add access of a group for a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group to add access for
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Group access added successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	public function addGroup(int $id, string $group): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->addApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Remove access of a group from a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group to remove access from
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Group access removed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	public function removeGroup(int $id, string $group): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->removeApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Set the permissions of a group for a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group for which the permissions will be set
	 * @param int $permissions New permissions
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Permissions updated successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	public function setPermissions(int $id, string $group, int $permissions): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setGroupPermissions($id, $group, $permissions);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Updates an ACL mapping
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param string $mappingType Type of the ACL mapping
	 * @param string $mappingId ID of the ACL mapping
	 * @param bool $manageAcl Whether to enable or disable the ACL mapping
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: ACL mapping updated successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	public function setManageACL(int $id, string $mappingType, string $mappingId, bool $manageAcl): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setManageACL($id, $mappingType, $mappingId, $manageAcl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Set a new quota for a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param int $quota New quota in bytes
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: New quota set successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	public function setQuota(int $id, int $quota): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setFolderQuota($id, $quota);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Toggle the ACL for a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param bool $acl Whether ACL should be enabled or not
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: ACL toggled successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	public function setACL(int $id, bool $acl): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->setFolderACL($id, $acl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * Rename a Groupfolder
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 * @param int $id ID of the Groupfolder
	 * @param string $mountpoint New Mountpoint of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Groupfolder renamed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	public function renameFolder(int $id, string $mountpoint): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}
		$this->manager->renameFolder($id, trim($mountpoint));
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
		$groups = $data['group_details'] ?? [];
		unset($data['group_details']);
		$data['groups'] = [];
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
	 * Searches for matching ACL mappings
	 * @NoAdminRequired
	 * @param int $id The ID of the Groupfolder
	 * @param string $search String to search by
	 * @return DataResponse<Http::STATUS_OK, array{users: list<GroupFoldersUser>, groups: list<GroupFoldersGroup>, circles: list<GroupFoldersCircle>}, array{}>
	 * @throws OCSForbiddenException Not allowed to search
	 *
	 * 200: ACL Mappings returned
	 */
	public function aclMappingSearch(int $id, string $search = ''): DataResponse {
		$users = $groups = $circles = [];

		if ($this->manager->canManageACL($id, $this->user) === true) {
			$groups = $this->manager->searchGroups($id, $search);
			$users = $this->manager->searchUsers($id, $search);
			$circles = $this->manager->searchCircles($id, $search);
		}
		return new DataResponse([
			'users' => $users,
			'groups' => $groups,
			'circles' => $circles
		]);
	}
}
