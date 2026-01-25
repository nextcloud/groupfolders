<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\Connector\Sabre;

use OC\Files\Mount\MoveableMount;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\Files\View;
use OCA\DAV\Connector\Sabre\Exception\InvalidPath;
use OCP\Files\DavUtil;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\ISharedStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

abstract class Node implements \Sabre\DAV\INode {
	/**
	 * @var View
	 */
	protected $fileView;

	/**
	 * The path to the current node
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * node properties cache
	 *
	 * @var array
	 */
	protected $property_cache = null;

	protected FileInfo $info;

	/**
	 * @var IManager
	 */
	protected $shareManager;

	protected \OCP\Files\Node $node;

	/**
	 * Sets up the node, expects a full path name
	 */
	public function __construct(View $view, FileInfo $info, ?IManager $shareManager = null)
 {
 }

	protected function refreshInfo(): void
 {
 }

	/**
	 *  Returns the name of the node
	 *
	 * @return string
	 */
	public function getName()
 {
 }

	/**
	 * Returns the full path
	 *
	 * @return string
	 */
	public function getPath()
 {
 }

	/**
	 * Renames the node
	 *
	 * @param string $name The new name
	 * @throws \Sabre\DAV\Exception\BadRequest
	 * @throws \Sabre\DAV\Exception\Forbidden
	 */
	public function setName($name)
 {
 }

	public function setPropertyCache($property_cache)
 {
 }

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int timestamp as integer
	 */
	public function getLastModified()
 {
 }

	/**
	 *  sets the last modification time of the file (mtime) to the value given
	 *  in the second parameter or to now if the second param is empty.
	 *  Even if the modification time is set to a custom value the access time is set to now.
	 */
	public function touch($mtime)
 {
 }

	/**
	 * Returns the ETag for a file
	 *
	 * An ETag is a unique identifier representing the current version of the
	 * file. If the file changes, the ETag MUST change.  The ETag is an
	 * arbitrary string, but MUST be surrounded by double-quotes.
	 *
	 * Return null if the ETag can not effectively be determined
	 *
	 * @return string
	 */
	public function getETag()
 {
 }

	/**
	 * Sets the ETag
	 *
	 * @param string $etag
	 *
	 * @return int file id of updated file or -1 on failure
	 */
	public function setETag($etag)
 {
 }

	public function setCreationTime(int $time)
 {
 }

	public function setUploadTime(int $time)
 {
 }

	/**
	 * Returns the size of the node, in bytes
	 *
	 * @psalm-suppress ImplementedReturnTypeMismatch \Sabre\DAV\IFile::getSize signature does not support 32bit
	 * @return int|float
	 */
	public function getSize(): int|float
 {
 }

	/**
	 * Returns the cache's file id
	 *
	 * @return int
	 */
	public function getId()
 {
 }

	/**
	 * @return string|null
	 */
	public function getFileId()
 {
 }

	/**
	 * @return integer
	 */
	public function getInternalFileId()
 {
 }

	public function getInternalPath(): string
 {
 }

	/**
	 * @param string $user
	 * @return int
	 */
	public function getSharePermissions($user)
 {
 }

	/**
	 * @return array
	 */
	public function getShareAttributes(): array
 {
 }

	public function getNoteFromShare(?string $user): ?string
 {
 }

	/**
	 * @return string
	 */
	public function getDavPermissions()
 {
 }

	public function getOwner()
 {
 }

	protected function verifyPath(?string $path = null): void
 {
 }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 */
	public function acquireLock($type)
 {
 }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 */
	public function releaseLock($type)
 {
 }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 */
	public function changeLock($type)
 {
 }

	public function getFileInfo()
 {
 }

	public function getNode(): \OCP\Files\Node
 {
 }

	protected function sanitizeMtime(string $mtimeFromRequest): int
 {
 }
}
