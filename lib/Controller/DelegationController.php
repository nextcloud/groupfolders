<?php
namespace OCA\GroupFolders\Controller;

use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;

class DelegationController extends OCSController {

	/** @var IConfig */
	private $config;

	/** @var IGroupManager */
	private $groupManager;

	public function __construct($AppName,
			IConfig $config,
			IGroupManager $groupManager,
			IRequest $request) {
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->groupManager = $groupManager;
	}

	/**
	 * Returns the list of all groups
	 *
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 *
	 * @return DataResponse
	 */
	public function getAllGroups() {
		// Get all groups
		$groups = $this->groupManager->search('');

		// transform in a format suitable for the app
		$data = [];
		foreach($groups as $group) {
			$data[] = [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
				'usercount' => $group->count(),
				'disabled' => $group->countDisabled(),
				'canAdd' => $group->canAddUser(),
				'canRemove' => $group->canRemoveUser(),
			];
		}

		// return info
		return new DataResponse($data);
	}

	/**
	 * Get the list of groups allowed to use groupfolders
	 *
	 * @NoAdminRequired
	 * @RequireGroupFolderAdmin
	 *
	 * @return DataResponse
	 */
	public function getAllowedGroups() {
		$groups = json_decode($this->config->getAppValue('groupfolders', 'delegated-admins', '["admin"]'));

		// transform in a format suitable for the app
		$data = [];
		foreach($groups as $gid) {
			$group = $this->groupManager->get($gid);
			$data[] = [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
				'usercount' => $group->count(),
				'disabled' => $group->countDisabled(),
				'canAdd' => $group->canAddUser(),
				'canRemove' => $group->canRemoveUser(),
			];
		}
		return new DataResponse($data);
	}

	/**
	 * Update the list of groups allowed to use groupfolders
	 *
	 * @return DataResponse
	 */
	public function updateAllowedGroups($groups) {
		$this->config->setAppValue('groupfolders', 'delegated-admins', $groups);
		return new DataResponse([], Http::STATUS_OK);
	}

}
