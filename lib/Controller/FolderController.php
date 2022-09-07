<?php
/**
 * SPDX-FileCopyrightText: 2017 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	private FolderManager $manager;
	private MountProvider $mountProvider;
	private IRootFolder $rootFolder;
	private ?IUser $user = null;

	public function __construct(
		string $AppName,
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

		$this->registerResponder('xml', function ($data): V1Response {
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
	 */
	public function addFolder(string $mountpoint): DataResponse {
		$id = $this->manager->createFolder($mountpoint);
		return new DataResponse(['id' => $id]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
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
	 */
	public function setMountPoint(int $id, string $mountPoint): DataResponse {
		$this->manager->setMountPoint($id, $mountPoint);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 */
	public function addGroup(int $id, string $group): DataResponse {
		$this->manager->addApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 */
	public function removeGroup(int $id, string $group): DataResponse {
		$this->manager->removeApplicableGroup($id, $group);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 */
	public function setPermissions(int $id, string $group, int $permissions): DataResponse {
		$this->manager->setGroupPermissions($id, $group, $permissions);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 * @throws \OCP\DB\Exception
	 */
	public function setManageACL(int $id, string $mappingType, string $mappingId, bool $manageAcl): DataResponse {
		$this->manager->setManageACL($id, $mappingType, $mappingId, $manageAcl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 */
	public function setQuota(int $id, int $quota): DataResponse {
		$this->manager->setFolderQuota($id, $quota);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
	 */
	public function setACL(int $id, bool $acl): DataResponse {
		$this->manager->setFolderACL($id, $acl);
		return new DataResponse(['success' => true]);
	}

	/**
	 * @AuthorizedAdminSetting(settings=OCA\GroupFolders\Settings\Admin)
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
