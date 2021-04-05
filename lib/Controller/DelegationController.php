<?php
namespace OCA\GroupFolders\Controller;

use OCP\IConfig;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;

class DelegationController extends Controller {

	/** @var IConfig */
	private $config;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserManager */
	private $userManager;

	public function __construct($AppName,
			IConfig $config,
			IGroupManager $groupManager,
			IRequest $request,
			IUserManager $userManager) {
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}

	/**
	 * Returns the list of all groups
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
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
	 * Return true if user is member of a group that has been granted admin rights on groupfolders
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function isAdmin(string $userId) {
		$config = $this->config->getAppValue('groupfolders', 'delegated-admins', '["admin"]');
		$allowedGroups = str_word_count($config, 1, '-_');
		$user = $this->userManager->get($userId);
		$userGroups = $this->groupManager->getUserGroups($user);
		foreach($userGroups as $userGroup) {
			if (in_array($userGroup->getGID(), $allowedGroups)) {
				return new JSONResponse(true);
			}
		}
		return new JSONResponse(false);

	}

	/**
	 * Get the list of groups allowed to use groupfolders
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function getAllowedGroups() {
		$groups = explode(',', $this->config->getAppValue('groupfolders', 'delegated-admins', 'admin'));

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
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
	public function updateAllowedGroups($groups) {
		$this->config->setAppValue('groupfolders', 'delegated-admins', $groups);
		return new JSONResponse("ok");
	}

}
