<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
