<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Controller;

use OC\AppFramework\OCS\V1Response;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\GroupFolders\Attribute\RequireGroupFolderAdmin;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Service\DelegationService;
use OCA\GroupFolders\Service\FoldersFilter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\DB\Exception;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

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

		$this->registerResponder('xml', fn (DataResponse $data): V1Response => $this->buildOCSResponseXML('xml', $data));
	}

	/**
	 * Regular users can access their own folders, but they only get to see the permission for their own groups
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
	 * @param array{acl: bool, groups: array<string, array{displayName: string, type: string, permissions: int}>, id: int, manage: array<array-key, array{displayname?: string, id?: string, type?: "group"|"user"|"circle"}>, mount_point: mixed, quota: int, size: int} $folder
	 * @return array{acl: bool, group_details: array<string, array{displayName: string, type: string, permissions: int}>, groups: array<string, int>, id: int, manage: array<array-key, array{displayname?: string, id?: string, type?: "group"|"user"|"circle"}>, mount_point: mixed, quota: int, size: int}
	 */
	private function formatFolder(array $folder): array {
		// keep compatibility with the old 'groups' field
		$folder['group_details'] = $folder['groups'];
		$folder['groups'] = array_map(fn (array $group): int => $group['permissions'], $folder['groups']);

		return $folder;
	}

	/**
	 * @throws OCSNotFoundException
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/folders')]
	public function getFolders(bool $applicable = false): DataResponse {
		$storageId = $this->getRootFolderStorageId();
		if ($storageId === null) {
			throw new OCSNotFoundException();
		}

		$folders = $this->manager->getAllFoldersWithSize($storageId);
		$folders = array_map($this->formatFolder(...), $folders);
		$isAdmin = $this->delegationService->isAdminNextcloud() || $this->delegationService->isDelegatedAdmin();
		if ($isAdmin && !$applicable) {
			return new DataResponse($folders);
		}

		if ($this->delegationService->hasOnlyApiAccess()) {
			$folders = $this->foldersFilter->getForApiUser($folders);
		}

		if ($applicable || !$this->delegationService->hasApiAccess()) {
			$folders = array_map($this->filterNonAdminFolder(...), $folders);
			$folders = array_filter($folders);
		}

		return new DataResponse($folders);
	}

	/**
	 * @throws OCSNotFoundException
	 * @throws Exception
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
			throw new OCSNotFoundException();
		}

		$folder = $this->manager->getFolder($id, $storageId);
		if ($folder === null) {
			throw new OCSNotFoundException();
		}

		if (!$this->delegationService->hasApiAccess()) {
			$folder = $this->filterNonAdminFolder($folder);
			if ($folder === null) {
				throw new OCSNotFoundException();
			}
		}

		return new DataResponse($this->formatFolder($folder));
	}

	/**
	 * @throws Exception
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
	 * @throws OCSNotFoundException
	 * @throws Exception
	 */
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/folders')]
	public function addFolder(string $mountpoint): DataResponse {

		$storageId = $this->rootFolder->getMountPoint()->getNumericStorageId();
		if ($storageId === null) {
			throw new OCSNotFoundException();
		}

		$id = $this->manager->createFolder(trim($mountpoint));
		$folder = $this->manager->getFolder($id, $storageId);
		if ($folder === null) {
			throw new OCSNotFoundException();
		}

		return new DataResponse($folder);
	}

	/**
	 * @throws NotPermittedException
	 * @throws OCSNotFoundException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws Exception
	 */
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
	 * @throws Exception
	 */
	#[RequireGroupFolderAdmin]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/folders/{id}')]
	public function setMountPoint(int $id, string $mountPoint): DataResponse {
		$this->manager->renameFolder($id, trim($mountPoint));
		return new DataResponse(['success' => true]);
	}

	/**
	 * @throws RequestBuilderException
	 * @throws Exception
	 * @throws InitiatorNotFoundException
	 */
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
	 * @throws Exception
	 */
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
	 * @throws Exception
	 */
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
	 * @throws Exception
	 */
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
	 * @throws Exception
	 */
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
	 * @throws Exception
	 */
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
	 * @throws Exception
	 */
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
	 * Overwrite response builder to customize xml handling to deal with spaces in folder names
	 */
	private function buildOCSResponseXML(string $format, DataResponse $data): V1Response {
		/** @var array $folderData */
		$folderData = $data->getData();
		if (isset($folderData['id'])) {
			// single folder response
			$folderData = $this->folderDataForXML($folderData);
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
	 * @throws OCSForbiddenException
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/folders/{id}/search')]
	public function aclMappingSearch(int $id, ?int $fileId, string $search = ''): DataResponse {
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
