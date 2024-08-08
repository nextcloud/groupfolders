<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\DAV;

use OCA\GroupFolders\Folder\FolderManager;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use Sabre\DAVACL\AbstractPrincipalCollection;
use Sabre\DAVACL\PrincipalBackend;

class RootCollection extends AbstractPrincipalCollection {
	public function __construct(
		private IUserSession $userSession,
		PrincipalBackend\BackendInterface $principalBackend,
		private FolderManager $folderManager,
		private IRootFolder $rootFolder,
	) {
		parent::__construct($principalBackend, 'principals/users');
	}

	/**
	 * This method returns a node for a principal.
	 *
	 * The passed array contains principal information, and is guaranteed to
	 * at least contain a uri item. Other properties may or may not be
	 * supplied by the authentication backend.
	 *
	 * @param array $principalInfo
	 */
	public function getChildForPrincipal(array $principalInfo): GroupFoldersHome {
		[, $name] = \Sabre\Uri\split($principalInfo['uri']);
		$user = $this->userSession->getUser();
		if (is_null($user) || $name !== $user->getUID()) {
			throw new \Sabre\DAV\Exception\Forbidden();
		}
		return new GroupFoldersHome($principalInfo, $this->folderManager, $this->rootFolder, $user);
	}

	public function getName(): string {
		return 'groupfolders';
	}
}
