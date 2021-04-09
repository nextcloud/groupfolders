<?php
namespace OCA\GroupFolders\Controller;

use OCP\IConfig;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;

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
	 *
	 * @return JSONResponse
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
		return new JSONResponse($data);
	}

	/**
	 * Get the list of groups allowed to use groupfolders
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function getAllowedGroups() {
		$groups = explode('|', $this->config->getAppValue('groupfolders', 'delegated-admins', 'admin'));

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
		return new JSONResponse($data);
	}

	/**
	 * Update the list of groups allowed to use groupfolders
	 *
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function updateAllowedGroups($groups) {
		$this->config->setAppValue('groupfolders', 'delegated-admins', $groups);
		return new JSONResponse("ok");
	}

}
