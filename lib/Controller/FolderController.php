<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Controller;

use OCA\GroupFolders\Attribute\RequireGroupFolderAdmin;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\ResponseDefinitions;
use OCA\GroupFolders\Service\DelegationService;
use OCA\GroupFolders\Service\FoldersFilter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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
 * @psalm-import-type GroupFoldersUser from ResponseDefinitions
 * @psalm-import-type GroupFoldersFolder from ResponseDefinitions
 */
class FolderController extends OCSController {
	private ?IUser $user;

	public function __construct(
		string $AppName,
		IRequest $request,
		private FolderManager $manager,
		private MountProvider $mountProvider,
		private IRootFolder $rootFolder,
		IUserSession $userSession,
		private FoldersFilter $foldersFilter,
		private DelegationService $delegationService,
		private IGroupManager $groupManager,
	) {
		parent::__construct($AppName, $request);
		$this->user = $userSession->getUser();
	}

	/**
	 * Regular users can access their own folders, but they only get to see the permission for their own groups
	 *
	 * @param GroupFoldersFolder $folder
	 * @return GroupFoldersFolder|null
	 */
	private function filterNonAdminFolder(array $folder): ?array {
		if ($this->user === null) {
			return null;
		}

		$userGroups = $this->groupManager->getUserGroupIds($this->user);
		if (is_array($folder['groups'])) {
			$folder['groups'] = array_filter($folder['groups'], fn (string $group): bool => in_array($group, $userGroups), ARRAY_FILTER_USE_KEY);
			if (!empty($folder['groups'])) {
				return $folder;
			}
		}

		return null;
	}

	/**
	 * Gets all Groupfolders
	 *
	 * @param bool $applicable Filter by applicable groups
	 * @return DataResponse<Http::STATUS_OK, list<GroupFoldersFolder>, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Groupfolders returned
	 * 404: Storage not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/folders')]
	public function getFolders(bool $applicable = false): DataResponse {
		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$folders = $this->manager->getAllFoldersWithSize($storageId);
		$isAdmin = $this->delegationService->isAdminNextcloud() || $this->delegationService->isDelegatedAdmin();
		if ($isAdmin && !$applicable) {
			return new DataResponse($folders);
		}

		if ($this->delegationService->hasOnlyApiAccess()) {
			$folders = $this->foldersFilter->getForApiUser($folders);
		}

		if ($applicable || !$this->delegationService->hasApiAccess()) {
			$folders = array_values(array_filter(array_map($this->filterNonAdminFolder(...), $folders)));
		}

		return new DataResponse($folders);
	}

	/**
	 * Gets a Groupfolder by ID
	 *
	 * @param int $id ID of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, GroupFoldersFolder, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Groupfolder returned
	 * 404: Groupfolder not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/folders/{id}')]
	public function getFolder(int $id): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}

		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$folder = $this->manager->getFolder($id, $storageId);
		if ($folder === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$this->delegationService->hasApiAccess()) {
			$folder = $this->filterNonAdminFolder($folder);
			if ($folder === null) {
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
		if ($folder === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return null;
	}

	private function getRootFolderStorageId(): ?int {
		return $this->rootFolder->getMountPoint()->getNumericStorageId();
	}

	/**
	 * Add a new Groupfolder
	 *
	 * @param string $mountpoint Mountpoint of the new Groupfolder
	 * @return DataResponse<Http::STATUS_OK, GroupFoldersFolder, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Groupfolder added successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders')]
	public function addFolder(string $mountpoint): DataResponse {

		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$id = $this->manager->createFolder(trim($mountpoint));
		$folder = $this->manager->getFolder($id, $storageId);
		if ($folder === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse($folder);
	}

	/**
	 * Remove a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Groupfolder removed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/folders/{id}')]
	public function removeFolder(int $id): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}

		$folder = $this->mountProvider->getFolder($id);
		if ($folder === null) {
			throw new OCSNotFoundException();
		}

		$folder->delete();
		$this->manager->removeFolder($id);

		return new DataResponse(['success' => true]);
	}

	/**
	 * Add access of a group for a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group to add access for
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Group access added sucessfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders/{id}/groups')]
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
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group to remove access from
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Group access removed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/folders/{id}/groups/{group}', requirements: ['group' => '.+'])]
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
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group for which the permissions will be set
	 * @param int $permissions New permissions
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Permissions updated successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders/{id}/groups/{group}', requirements: ['group' => '.+'])]
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
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $mappingType Type of the ACL mapping
	 * @param string $mappingId ID of the ACL mapping
	 * @param bool $manageAcl Whether to enable or disable the ACL mapping
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 * @throws \OCP\DB\Exception
	 *
	 * 200: ACL mapping updated successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders/{id}/manageACL')]
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
	 *
	 * @param int $id ID of the Groupfolder
	 * @param int $quota New quota in bytes
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: New quota set successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders/{id}/quota')]
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
	 *
	 * @param int $id ID of the Groupfolder
	 * @param bool $acl Whether ACL should be enabled or not
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: ACL toggled successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders/{id}/acl')]
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
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $mountpoint New Mountpoint of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Groupfolder renamed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders/{id}/mountpoint')]
	public function renameFolder(int $id, string $mountpoint): DataResponse {
		$response = $this->checkFolderExists($id);
		if ($response) {
			return $response;
		}

		$this->manager->renameFolder($id, trim($mountpoint));

		return new DataResponse(['success' => true]);
	}

	/**
	 * Searches for matching ACL mappings
	 *
	 * @param int $id The ID of the Groupfolder
	 * @param string $search String to search by
	 * @return DataResponse<Http::STATUS_OK, array{users: list<GroupFoldersUser>, groups: list<GroupFoldersGroup>}, array{}>
	 *
	 * 200: ACL Mappings returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/folders/{id}/search')]
	public function aclMappingSearch(int $id, string $search = ''): DataResponse {
		$users = [];
		$groups = [];

		if ($this->user === null) {
			throw new OCSForbiddenException();
		}

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
