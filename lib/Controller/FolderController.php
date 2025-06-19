<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Controller;

use OC\AppFramework\OCS\V1Response;
use OCA\GroupFolders\Attribute\RequireGroupFolderAdmin;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\ResponseDefinitions;
use OCA\GroupFolders\Service\DelegationService;
use OCA\GroupFolders\Service\FoldersFilter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\Folder;
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
	private readonly ?IUser $user;

	protected const ALLOWED_ORDER_BY = [
		'mount_point',
		'quota',
		'groups',
		'acl',
	];

	public function __construct(
		string $AppName,
		IRequest $request,
		private readonly FolderManager $manager,
		private readonly MountProvider $mountProvider,
		private readonly IRootFolder $rootFolder,
		IUserSession $userSession,
		private readonly FoldersFilter $foldersFilter,
		private readonly DelegationService $delegationService,
		private readonly IGroupManager $groupManager,
	) {
		parent::__construct($AppName, $request);
		$this->user = $userSession->getUser();

		$this->registerResponder('xml', fn (DataResponse $data): V1Response => $this->buildOCSResponseXML('xml', $data));
	}

	/**
	 * Regular users can access their own folders, but they only get to see the permission for their own groups
	 *
	 * @param GroupFoldersFolder $folder
	 * @return null|GroupFoldersFolder
	 */
	private function filterNonAdminFolder(array $folder): ?array {
		if ($this->user === null) {
			return null;
		}

		$userGroups = $this->groupManager->getUserGroupIds($this->user);
		$folder['groups'] = array_filter($folder['groups'], fn (string $group): bool => in_array($group, $userGroups), ARRAY_FILTER_USE_KEY);
		if ($folder['groups']) {
			return $folder;
		} else {
			return null;
		}
	}

	/**
	 * @param InternalFolderOut $folder
	 * @return GroupFoldersFolder
	 */
	private function formatFolder(array $folder): array {
		// keep compatibility with the old 'groups' field
		$folder['group_details'] = $folder['groups'];
		$folder['groups'] = array_map(fn (array $group): int => $group['permissions'], $folder['groups']);

		return $folder;
	}

	/**
	 * Gets all Groupfolders
	 *
	 * @param bool $applicable Filter by applicable groups
	 * @param non-negative-int $offset Number of items to skip.
	 * @param ?positive-int $limit Number of items to return.
	 * @param null|'mount_point'|'quota'|'groups'|'acl' $orderBy The key to order by
	 * @return DataResponse<Http::STATUS_OK, array<string, GroupFoldersFolder>, array{}>
	 * @throws OCSNotFoundException Storage not found
	 * @throws OCSBadRequestException Wrong limit used
	 *
	 * 200: Groupfolders returned
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folders')]
	public function getFolders(bool $applicable = false, int $offset = 0, ?int $limit = null, ?string $orderBy = 'mount_point'): DataResponse {
		if ($limit !== null && $limit <= 0) {
			throw new OCSBadRequestException('The limit must be greater than 0.');
		}

		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			throw new OCSNotFoundException();
		}

		$folders = [];
		foreach ($this->manager->getAllFoldersWithSize($storageId) as $id => $folder) {
			// Make them string-indexed for OpenAPI JSON output
			$folders[(string)$id] = $this->formatFolder($folder);
		}

		$orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true)
			? $orderBy
			: 'mount_point';

		// in case of equal orderBy value always fall back to the mount_point - same as on the frontend
		/**
		 * @var GroupFoldersFolder $a
		 * @var GroupFoldersFolder $b
		 */
		uasort($folders, function (array $a, array $b) use ($orderBy) {
			if ($orderBy === 'groups') {
				if (($value = count($a['groups']) - count($b['groups'])) !== 0) {
					return $value;
				}
			} else {
				if (($value = strcmp((string)($a[$orderBy] ?? ''), (string)($b[$orderBy] ?? ''))) !== 0) {
					return $value;
				}
			}

			// fallback to mount_point
			if (($value = strcmp($a['mount_point'] ?? '', $b['mount_point'])) !== 0) {
				return $value;
			}

			// fallback to id
			return $a['id'] - $b['id'];
		});

		$isAdmin = $this->delegationService->isAdminNextcloud() || $this->delegationService->isDelegatedAdmin();
		if ($isAdmin && !$applicable) {
			// If only the default values are provided the pagination can be skipped.
			if ($offset !== 0 || $limit !== null) {
				$folders = array_slice($folders, $offset, $limit, true);
			}

			return new DataResponse($folders);
		}

		if ($this->delegationService->hasOnlyApiAccess()) {
			$folders = $this->foldersFilter->getForApiUser($folders);
		}

		if ($applicable || !$this->delegationService->hasApiAccess()) {
			$folders = array_filter(array_map($this->filterNonAdminFolder(...), $folders));
		}

		// If only the default values are provided the pagination can be skipped.
		if ($offset !== 0 || $limit !== null) {
			$folders = array_slice($folders, $offset, $limit, true);
		}

		return new DataResponse($folders);
	}

	/**
	 * Gets a Groupfolder by ID
	 *
	 * @param int $id ID of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, GroupFoldersFolder, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 *
	 * 200: Groupfolder returned
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folders/{id}')]
	public function getFolder(int $id): DataResponse {
		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			throw new OCSNotFoundException();
		}

		$this->checkFolderExists($id);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);
		$folder = $this->formatFolder($folder);

		if (!$this->delegationService->hasApiAccess()) {
			$folder = $this->filterNonAdminFolder($folder);
			if ($folder === null) {
				throw new OCSNotFoundException();
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
			throw new OCSNotFoundException('Groupfolder not found');
		}

		$folder = $this->manager->getFolder($id);
		if ($folder === null) {
			throw new OCSNotFoundException('Groupfolder not found');
		}

		return null;
	}

	private function checkMountPointExists(string $mountpoint): ?DataResponse {
		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			throw new OCSNotFoundException('Groupfolder not found');
		}

		$folders = $this->manager->getAllFolders();
		foreach ($folders as $folder) {
			if ($folder['mount_point'] === $mountpoint) {
				throw new OCSBadRequestException('Mount point already exists');
			}
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
	 * @return DataResponse<Http::STATUS_OK, GroupFoldersFolder, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 * @throws OCSBadRequestException Folder already exists
	 *
	 * 200: Groupfolder added successfully
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folders')]
	public function addFolder(string $mountpoint): DataResponse {
		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			throw new OCSNotFoundException();
		}

		$this->checkMountPointExists(trim($mountpoint));

		$id = $this->manager->createFolder(trim($mountpoint));
		$this->checkFolderExists($id);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse($this->formatFolder($folder));
	}

	/**
	 * Remove a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 *
	 * 200: Groupfolder removed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/folders/{id}')]
	public function removeFolder(int $id): DataResponse {
		$this->checkFolderExists($id);

		/** @var Folder */
		$folder = $this->mountProvider->getFolder($id);
		$folder->delete();
		$this->manager->removeFolder($id);

		return new DataResponse(['success' => true]);
	}

	/**
	 * Set the mount point of a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $mountPoint New mount point path
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 * @throws OCSBadRequestException Mount point already exists
	 *
	 * 200: Mount point changed successfully
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/folders/{id}')]
	public function setMountPoint(int $id, string $mountPoint): DataResponse {
		$this->manager->renameFolder($id, trim($mountPoint));

		$this->checkFolderExists($id);
		$this->checkMountPointExists(trim($mountPoint));

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Add access of a group for a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group to add access for
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Group access added successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folders/{id}/groups')]
	public function addGroup(int $id, string $group): DataResponse {
		$this->checkFolderExists($id);

		$this->manager->addApplicableGroup($id, $group);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Remove access of a group from a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group to remove access from
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Group access removed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/folders/{id}/groups/{group}', requirements: ['group' => '.+'])]
	public function removeGroup(int $id, string $group): DataResponse {
		$this->checkFolderExists($id);

		$this->manager->removeApplicableGroup($id, $group);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Set the permissions of a group for a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $group Group for which the permissions will be set
	 * @param int $permissions New permissions
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Permissions updated successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folders/{id}/groups/{group}', requirements: ['group' => '.+'])]
	public function setPermissions(int $id, string $group, int $permissions): DataResponse {
		$this->checkFolderExists($id);

		$this->manager->setGroupPermissions($id, $group, $permissions);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Updates an ACL mapping
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $mappingType Type of the ACL mapping
	 * @param string $mappingId ID of the ACL mapping
	 * @param bool $manageAcl Whether to enable or disable the ACL mapping
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: ACL mapping updated successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folders/{id}/manageACL')]
	public function setManageACL(int $id, string $mappingType, string $mappingId, bool $manageAcl): DataResponse {
		$this->checkFolderExists($id);

		$this->manager->setManageACL($id, $mappingType, $mappingId, $manageAcl);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Set a new quota for a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param int $quota New quota in bytes
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: New quota set successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folders/{id}/quota')]
	public function setQuota(int $id, int $quota): DataResponse {
		$this->checkFolderExists($id);

		$this->manager->setFolderQuota($id, $quota);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Toggle the ACL for a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param bool $acl Whether ACL should be enabled or not
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: ACL toggled successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folders/{id}/acl')]
	public function setACL(int $id, bool $acl): DataResponse {
		$this->checkFolderExists($id);

		$this->manager->setFolderACL($id, $acl);

		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Rename a Groupfolder
	 *
	 * @param int $id ID of the Groupfolder
	 * @param string $mountpoint New Mountpoint of the Groupfolder
	 * @return DataResponse<Http::STATUS_OK, array{success: true, folder: GroupFoldersFolder}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 * @throws OCSNotFoundException Groupfolder not found
	 * @throws OCSBadRequestException Mount point already exists or invalid mount point provided
	 *
	 * 200: Groupfolder renamed successfully
	 * 404: Groupfolder not found
	 */
	#[PasswordConfirmationRequired]
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/folders/{id}/mountpoint')]
	public function renameFolder(int $id, string $mountpoint): DataResponse {
		$this->checkFolderExists($id);

		// Check if the new mountpoint is valid
		if (empty($mountpoint)) {
			throw new OCSBadRequestException('Mount point cannot be empty');
		}

		// Check if we actually need to do anything
		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		if ($folder['mount_point'] === trim($mountpoint)) {
			return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
		}

		$this->checkMountPointExists(trim($mountpoint));
		$this->manager->renameFolder($id, trim($mountpoint));

		// Get the new folder data
		/** @var InternalFolderOut */
		$folder = $this->manager->getFolder($id);

		return new DataResponse(['success' => true, 'folder' => $this->formatFolder($folder)]);
	}

	/**
	 * Overwrite response builder to customize xml handling to deal with spaces in folder names
	 */
	private function buildOCSResponseXML(string $format, DataResponse $data): V1Response {
		/** @var array $folderData */
		$folderData = $data->getData();
		if (isset($folderData['id'])) {
			// single folder response
			$folderData = $this->folderDataForXML($folderData);
		} elseif (isset($folderData['folder'])) {
			// single folder response
			$folderData['folder'] = $this->folderDataForXML(['folder']);
		} elseif (is_array($folderData) && count($folderData) && isset(current($folderData)['id'])) {
			// folder list
			$folderData = array_map($this->folderDataForXML(...), $folderData);
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
	 *
	 * @param int $id The ID of the Groupfolder
	 * @param string $search String to search by
	 * @return DataResponse<Http::STATUS_OK, array{users: list<GroupFoldersUser>, groups: list<GroupFoldersGroup>, circles: list<GroupFoldersCircle>}, array{}>
	 * @throws OCSForbiddenException Not allowed to search
	 *
	 * 200: ACL Mappings returned
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/folders/{id}/search')]
	public function aclMappingSearch(int $id, string $search = ''): DataResponse {
		$users = $groups = $circles = [];

		if ($this->user === null) {
			throw new OCSForbiddenException();
		}

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
