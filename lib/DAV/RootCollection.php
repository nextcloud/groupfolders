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
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAVACL\AbstractPrincipalCollection;
use Sabre\DAVACL\PrincipalBackend;

/**
 * WebDAV root collection for the GroupFolders app.
 *
 * Provides access to user principal nodes representing each user's group folders home.
 */
class RootCollection extends AbstractPrincipalCollection {
	public function __construct(
		private readonly IUserSession $userSession,
		PrincipalBackend\BackendInterface $principalBackend,
		private readonly FolderManager $folderManager,
		private readonly IRootFolder $rootFolder,
	) {
		parent::__construct($principalBackend, 'principals/users');
	}

	/**
	 * Returns a GroupFoldersHome for the principal if the authenticated user matches.
	 *
	 * The passed array contains principal information, and is guaranteed to
	 * at least contain a uri item. Other properties may or may not be
	 * supplied by the authentication backend.
	 *
	 * @throws \Sabre\DAV\Exception\Forbidden If the principal does not match the currently logged-in user.
	 */
	public function getChildForPrincipal(array $principalInfo): GroupFoldersHome {
		[, $name] = \Sabre\Uri\split($principalInfo['uri']);
		$user = $this->userSession->getUser();

		if (is_null($user) || $name !== $user->getUID()) {
			throw new Forbidden('Access to this groupfolders principal is not allowed for this user.');
		}

		return new GroupFoldersHome($principalInfo, $this->folderManager, $this->rootFolder, $user);
	}

	public function getName(): string {
		return 'groupfolders';
	}
}
