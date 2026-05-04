<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Trashbin\Trash;

use OCP\Files\Cache\ICacheEntry;
use OCP\Files\FileInfo;
use OCP\IUser;

class TrashItem implements ITrashItem {

	public function __construct(
		private ITrashBackend $backend,
		private string $originalLocation,
		private int $deletedTime,
		private string $trashPath,
		private FileInfo $fileInfo,
		private IUser $user,
		private ?IUser $deletedBy,
	) {
	}

	#[\Override]
    public function getTrashBackend(): ITrashBackend
    {
    }

	#[\Override]
    public function getOriginalLocation(): string
    {
    }

	#[\Override]
    public function getDeletedTime(): int
    {
    }

	#[\Override]
    public function getTrashPath(): string
    {
    }

	#[\Override]
    public function isRootItem(): bool
    {
    }

	#[\Override]
    public function getUser(): IUser
    {
    }

	#[\Override]
    public function getEtag()
    {
    }

	#[\Override]
    public function getSize($includeMounts = true)
    {
    }

	#[\Override]
    public function getMtime()
    {
    }

	#[\Override]
    public function getName()
    {
    }

	#[\Override]
    public function getInternalPath()
    {
    }

	#[\Override]
    public function getPath()
    {
    }

	#[\Override]
    public function getMimetype(): string
    {
    }

	#[\Override]
    public function getMimePart()
    {
    }

	#[\Override]
    public function getStorage()
    {
    }

	#[\Override]
    public function getId()
    {
    }

	#[\Override]
    public function isEncrypted()
    {
    }

	#[\Override]
    public function getPermissions()
    {
    }

	#[\Override]
    public function getType()
    {
    }

	#[\Override]
    public function isReadable()
    {
    }

	#[\Override]
    public function isUpdateable()
    {
    }

	#[\Override]
    public function isCreatable()
    {
    }

	#[\Override]
    public function isDeletable()
    {
    }

	#[\Override]
    public function isShareable()
    {
    }

	#[\Override]
    public function isShared()
    {
    }

	#[\Override]
    public function isMounted()
    {
    }

	#[\Override]
    public function getMountPoint()
    {
    }

	#[\Override]
    public function getOwner()
    {
    }

	#[\Override]
    public function getChecksum()
    {
    }

	#[\Override]
    public function getExtension(): string
    {
    }

	#[\Override]
    public function getTitle(): string
    {
    }

	#[\Override]
    public function getCreationTime(): int
    {
    }

	#[\Override]
    public function getUploadTime(): int
    {
    }

	#[\Override]
    public function getLastActivity(): int
    {
    }

	#[\Override]
    public function getParentId(): int
    {
    }

	#[\Override]
    public function getDeletedBy(): ?IUser
    {
    }

	/**
	 * @inheritDoc
	 * @return array<string, int|string|bool|float|string[]|int[]>
	 */
	#[\Override]
    public function getMetadata(): array
    {
    }

	#[\Override]
    public function getData(): ICacheEntry
    {
    }
}
