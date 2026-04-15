<?php

declare(strict_types=1);

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
use OCP\Constants;
use OCP\Files\DavUtil;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\ISharedStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;
use OCP\Server;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use RuntimeException;
use Sabre\DAV\Exception;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\INode;

abstract class Node implements INode {
	/**
	 * The path to the current node
	 */
	protected string $path;

	protected FileInfo $info;

	protected IManager $shareManager;

	protected \OCP\Files\Node $node;

	/**
	 * Sets up the node, expects a full path name
	 * @throws PreConditionNotMetException
	 */
	public function __construct(protected View $fileView, FileInfo $info, ?IManager $shareManager = null)
 {
 }

	/**
	 * @throws Exception
	 * @throws PreConditionNotMetException
	 */
	protected function refreshInfo(): void
 {
 }

	/**
	 *  Returns the name of the node
	 */
	public function getName(): string
 {
 }

	/**
	 * Returns the full path
	 */
	public function getPath(): string
 {
 }

	/**
	 * Check if this node can be renamed
	 */
	public function canRename(): bool
 {
 }

	/**
	 * Renames the node
	 *
	 * @param string $name The new name
	 * @throws Exception
	 * @throws Forbidden
	 * @throws InvalidPath
	 * @throws PreConditionNotMetException
	 * @throws LockedException
	 */
	public function setName($name): void
 {
 }

	/**
	 * Returns the last modification time, as a unix timestamp
	 *
	 * @return int timestamp as integer
	 */
	public function getLastModified(): int
 {
 }

	/**
	 *  sets the last modification time of the file (mtime) to the value given
	 *  in the second parameter or to now if the second param is empty.
	 *  Even if the modification time is set to a custom value the access time is set to now.
	 */
	public function touch(string $mtime): void
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
	 */
	public function getETag(): string
 {
 }

	/**
	 * Sets the ETag
	 *
	 * @return int file id of updated file or -1 on failure
	 */
	public function setETag(string $etag): int
 {
 }

	public function setCreationTime(int $time): int
 {
 }

	/**
	 * Returns the size of the node, in bytes
	 *
	 * @psalm-suppress UnusedPsalmSuppress psalm:strict actually thinks there is no mismatch, idk lol
	 * @psalm-suppress ImplementedReturnTypeMismatch \Sabre\DAV\IFile::getSize signature does not support 32bit
	 */
	public function getSize(): int|float
 {
 }

	/**
	 * Returns the cache's file id
	 */
	public function getId(): ?int
 {
 }

	public function getFileId(): ?string
 {
 }

	public function getInternalFileId(): ?int
 {
 }

	public function getInternalPath(): string
 {
 }

	public function getSharePermissions(?string $user): int
 {
 }

	public function getShareAttributes(): array
 {
 }

	public function getNoteFromShare(?string $user): ?string
 {
 }

	public function getDavPermissions(): string
 {
 }

	/**
	 * Returns the DAV Permissions with share and mount infromation stripped.
	 */
	public function getPublicDavPermissions(): string
 {
 }

	public function getOwner(): ?IUser
 {
 }

	/**
	 * @throws InvalidPath
	 */
	protected function verifyPath(?string $path = null): void
 {
 }

	/**
	 * @param ILockingProvider::LOCK_* $type
	 * @throws LockedException
	 */
	public function acquireLock($type): void
 {
 }

	/**
	 * @param ILockingProvider::LOCK_* $type
	 * @throws LockedException
	 */
	public function releaseLock($type): void
 {
 }

	/**
	 * @param ILockingProvider::LOCK_* $type
	 * @throws LockedException
	 */
	public function changeLock($type): void
 {
 }

	public function getFileInfo(): FileInfo
 {
 }

	public function getNode(): \OCP\Files\Node
 {
 }

	protected function sanitizeMtime(string $mtimeFromRequest): int
 {
 }
}
