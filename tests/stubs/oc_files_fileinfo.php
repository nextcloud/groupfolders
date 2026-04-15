<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files;

use OC\Files\Mount\HomeMountPoint;
use OCA\Files_Sharing\External\Mount;
use OCA\Files_Sharing\ISharedMountPoint;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Mount\IMountPoint;
use OCP\IUser;

/**
 * @template-implements \ArrayAccess<string,mixed>
 */
class FileInfo implements \OCP\Files\FileInfo, \ArrayAccess {
	/**
	 * @param string $path
	 * @param Storage\Storage $storage
	 * @param string $internalPath
	 * @param array|ICacheEntry $data
	 * @param IMountPoint $mount
	 * @param ?IUser $owner
	 */
	public function __construct(private $path, private $storage, private $internalPath, private array|ICacheEntry $data, $mount, private ?IUser $owner = null)
 {
 }

	public function offsetSet($offset, $value): void
 {
 }

	public function offsetExists($offset): bool
 {
 }

	public function offsetUnset($offset): void
 {
 }

	public function offsetGet(mixed $offset): mixed
 {
 }

	/**
	 * @return string
	 */
	public function getPath()
 {
 }

	public function getStorage()
 {
 }

	/**
	 * @return string
	 */
	public function getInternalPath()
 {
 }

	/**
	 * Get FileInfo ID or null in case of part file
	 *
	 * @return int|null
	 */
	public function getId()
 {
 }

	public function getMimetype(): string
 {
 }

	/**
	 * @return string
	 */
	public function getMimePart()
 {
 }

	/**
	 * @return string
	 */
	public function getName()
 {
 }

	/**
	 * @return string
	 */
	public function getEtag()
 {
 }

	/**
	 * @param bool $includeMounts
	 * @return int|float
	 */
	public function getSize($includeMounts = true)
 {
 }

	/**
	 * @return int
	 */
	public function getMTime()
 {
 }

	/**
	 * @return bool
	 */
	public function isEncrypted()
 {
 }

	/**
	 * Return the current version used for the HMAC in the encryption app
	 */
	public function getEncryptedVersion(): int
 {
 }

	/**
	 * @return int
	 */
	public function getPermissions()
 {
 }

	/**
	 * @return string \OCP\Files\FileInfo::TYPE_FILE|\OCP\Files\FileInfo::TYPE_FOLDER
	 */
	public function getType()
 {
 }

	public function getData()
 {
 }

	/**
	 * @param int $permissions
	 * @return bool
	 */
	protected function checkPermissions($permissions)
 {
 }

	/**
	 * @return bool
	 */
	public function isReadable()
 {
 }

	/**
	 * @return bool
	 */
	public function isUpdateable()
 {
 }

	/**
	 * Check whether new files or folders can be created inside this folder
	 *
	 * @return bool
	 */
	public function isCreatable()
 {
 }

	/**
	 * @return bool
	 */
	public function isDeletable()
 {
 }

	/**
	 * @return bool
	 */
	public function isShareable()
 {
 }

	/**
	 * Check if a file or folder is shared
	 *
	 * @return bool
	 */
	public function isShared()
 {
 }

	public function isMounted()
 {
 }

	/**
	 * Get the mountpoint the file belongs to
	 *
	 * @return \OCP\Files\Mount\IMountPoint
	 */
	public function getMountPoint()
 {
 }

	/**
	 * Get the owner of the file
	 *
	 * @return ?IUser
	 */
	public function getOwner()
 {
 }

	/**
	 * @param IMountPoint[] $mounts
	 */
	public function setSubMounts(array $mounts)
 {
 }

	/**
	 * Add a cache entry which is the child of this folder
	 *
	 * Sets the size, etag and size to for cross-storage childs
	 *
	 * @param array|ICacheEntry $data cache entry for the child
	 * @param string $entryPath full path of the child entry
	 */
	public function addSubEntry($data, $entryPath)
 {
 }

	/**
	 * @inheritdoc
	 */
	public function getChecksum()
 {
 }

	public function getExtension(): string
 {
 }

	public function getCreationTime(): int
 {
 }

	public function getUploadTime(): int
 {
 }

	public function getLastActivity(): int
 {
 }

	public function getParentId(): int
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
