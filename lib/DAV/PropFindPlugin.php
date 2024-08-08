<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\DAV;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class PropFindPlugin extends ServerPlugin {
	private ?Folder $userFolder = null;

	public const MOUNT_POINT_PROPERTYNAME = '{http://nextcloud.org/ns}mount-point';
	public const GROUP_FOLDER_ID_PROPERTYNAME = '{http://nextcloud.org/ns}group-folder-id';

	public function __construct(IRootFolder $rootFolder, IUserSession $userSession) {
		$user = $userSession->getUser();
		if ($user === null) {
			return;
		}

		$this->userFolder = $rootFolder->getUserFolder($user->getUID());
	}


	public function getPluginName(): string {
		return 'groupFoldersDavPlugin';
	}

	public function initialize(Server $server): void {
		$server->on('propFind', [$this, 'propFind']);
	}

	public function propFind(PropFind $propFind, INode $node): void {
		if ($this->userFolder === null) {
			return;
		}

		if ($node instanceof GroupFolderNode) {
			$propFind->handle(
				self::MOUNT_POINT_PROPERTYNAME,
				fn () => $this->userFolder->getRelativePath($node->getFileInfo()->getMountPoint()->getMountPoint())
			);
			$propFind->handle(
				self::GROUP_FOLDER_ID_PROPERTYNAME,
				fn () => $node->getFolderId()
			);
		}
	}
}
