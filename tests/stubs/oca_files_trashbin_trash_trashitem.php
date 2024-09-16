<?php
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Trashbin\Trash;

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

	public function getTrashBackend(): ITrashBackend
 {
 }

	public function getOriginalLocation(): string
 {
 }

	public function getDeletedTime(): int
 {
 }

	public function getTrashPath(): string
 {
 }

	public function isRootItem(): bool
 {
 }

	public function getUser(): IUser
 {
 }

	public function getEtag()
 {
 }

	public function getSize($includeMounts = true)
 {
 }

	public function getMtime()
 {
 }

	public function getName()
 {
 }

	public function getInternalPath()
 {
 }

	public function getPath()
 {
 }

	public function getMimetype()
 {
 }

	public function getMimePart()
 {
 }

	public function getStorage()
 {
 }

	public function getId()
 {
 }

	public function isEncrypted()
 {
 }

	public function getPermissions()
 {
 }

	public function getType()
 {
 }

	public function isReadable()
 {
 }

	public function isUpdateable()
 {
 }

	public function isCreatable()
 {
 }

	public function isDeletable()
 {
 }

	public function isShareable()
 {
 }

	public function isShared()
 {
 }

	public function isMounted()
 {
 }

	public function getMountPoint()
 {
 }

	public function getOwner()
 {
 }

	public function getChecksum()
 {
 }

	public function getExtension(): string
 {
 }

	public function getTitle(): string
 {
 }

	public function getCreationTime(): int
 {
 }

	public function getUploadTime(): int
 {
 }

	public function getParentId(): int
 {
 }

	public function getDeletedBy(): ?IUser
 {
 }

	/**
	 * @inheritDoc
	 * @return array<string, int|string|bool|float|string[]|int[]>
	 */
	public function getMetadata(): array
 {
 }
}
