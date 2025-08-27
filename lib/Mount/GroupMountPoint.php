<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Storage;
use OC\Files\Storage\Wrapper\Jail;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCP\Files\Mount\IShareOwnerlessMount;
use OCP\Files\Mount\ISystemMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;

class GroupMountPoint extends MountPoint implements ISystemMountPoint, IShareOwnerlessMount {
	public function __construct(
		private readonly FolderDefinition $folder,
		IStorage $storage,
		string $mountpoint,
		?array $arguments = null,
		?IStorageFactory $loader = null,
		?array $mountOptions = null,
		?int $mountId = null,
		?int $rootId = null,
	) {
		/** @var Storage $storage */
		parent::__construct($storage, $mountpoint, $arguments, $loader, $mountOptions, $mountId, MountProvider::class);
		$this->rootId = $rootId;
	}

	public function getMountType(): string {
		return 'group';
	}

	public function getFolderId(): int {
		return $this->folder->id;
	}

	public function getFolder(): FolderDefinition {
		return $this->folder;
	}

	public function getSourcePath(): string {
		$storage = $this->storage;
		if ($storage && $storage->instanceOfStorage(Jail::class)) {
			/** @var Jail $storage */
			return $storage->getUnJailedPath('');
		} else {
			return '';
		}
	}
}
