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

/**
 * SabreDAV plugin that adds group folder metadata to PROPFIND responses.
 *
 * Adds the mount point and group folder ID as custom WebDAV properties for group folder nodes.
 */
class PropFindPlugin extends ServerPlugin {
	private ?Folder $userFolder = null;

	public const MOUNT_POINT_PROPERTYNAME = '{http://nextcloud.org/ns}mount-point';
	public const GROUP_FOLDER_ID_PROPERTYNAME = '{http://nextcloud.org/ns}group-folder-id';

	public function __construct(
		IRootFolder $rootFolder,
		IUserSession $userSession
	) {
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
		$server->on('propFind', $this->propFind(...));
	}

	public function propFind(PropFind $propFind, INode $node): void {
		if (!($node instanceof GroupFolderNode) || $this->userFolder === null) {
			return;
		}

		$propFind->handle(
			self::MOUNT_POINT_PROPERTYNAME,
			fn() => $this->getRelativeMountPointPath($node)
		);
		$propFind->handle(
			self::GROUP_FOLDER_ID_PROPERTYNAME,
			fn (): int => $node->getFolderId()
		);
	}

	/**
	 * Compute the path of the mount point relative to the root of the current user's folder.
	 *
	 * TODO: This may be a candidate for a utility function in GF or API addition in core.
	 */
	private function getRelativeMountPointPath(GroupFolderNode $node): ?string {
		// TODO: Seems there could be some more defensive null/error handling here (perhaps throwing a 404/not found + logging)
		$fileInfo = $node->getFileInfo();
		$mount = $fileInfo->getMountPoint();
		$mountPointPath = $mount->getMountPoint();
		return $this->userFolder->getRelativePath($mountPointPath);
	}
}
