<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Node;

use OC\Files\Filesystem;
use OC\Files\Mount\MoveableMount;
use OC\Files\Utils\PathHelper;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node as INode;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;

// FIXME: this class really should be abstract (+1)
class Node implements INode {
	/**
	 * @var \OC\Files\View $view
	 */
	protected $view;

	protected IRootFolder $root;

	/**
	 * @var string $path Absolute path to the node (e.g. /admin/files/folder/file)
	 */
	protected $path;

	protected ?FileInfo $fileInfo;

	protected ?INode $parent;

	/**
	 * @param \OC\Files\View $view
	 * @param \OCP\Files\IRootFolder $root
	 * @param string $path
	 * @param FileInfo $fileInfo
	 */
	public function __construct(IRootFolder $root, $view, $path, $fileInfo = null, ?INode $parent = null, bool $infoHasSubMountsIncluded = true)
 {
 }

	/**
	 * Creates a Node of the same type that represents a non-existing path
	 *
	 * @param string $path path
	 * @return Node non-existing node
	 * @throws \Exception
	 */
	protected function createNonExistingNode($path)
 {
 }

	/**
	 * Returns the matching file info
	 *
	 * @return FileInfo
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getFileInfo(bool $includeMountPoint = true)
 {
 }

	/**
	 * @param string[] $hooks
	 */
	protected function sendHooks($hooks, ?array $args = null)
 {
 }

	/**
	 * @param int $permissions
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	protected function checkPermissions($permissions)
 {
 }

	public function delete() {
	}

	/**
	 * @param int $mtime
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function touch($mtime = null)
 {
 }

	public function getStorage()
 {
 }

	/**
	 * @return string
	 */
	public function getPath()
 {
 }

	/**
	 * @return string
	 */
	public function getInternalPath()
 {
 }

	/**
	 * @return int
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getId()
 {
 }

	/**
	 * @return array
	 */
	public function stat()
 {
 }

	/**
	 * @return int
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getMTime()
 {
 }

	/**
	 * @param bool $includeMounts
	 * @return int|float
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getSize($includeMounts = true): int|float
 {
 }

	/**
	 * @return string
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getEtag()
 {
 }

	/**
	 * @return int
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getPermissions()
 {
 }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function isReadable()
 {
 }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function isUpdateable()
 {
 }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function isDeletable()
 {
 }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function isShareable()
 {
 }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function isCreatable()
 {
 }

	public function getParent(): INode|IRootFolder
 {
 }

	/**
	 * @return string
	 */
	public function getName()
 {
 }

	/**
	 * @param string $path
	 * @return string
	 */
	protected function normalizePath($path)
 {
 }

	/**
	 * check if the requested path is valid
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isValidPath($path)
 {
 }

	public function isMounted()
 {
 }

	public function isShared()
 {
 }

	public function getMimeType()
 {
 }

	public function getMimePart()
 {
 }

	public function getType()
 {
 }

	public function isEncrypted()
 {
 }

	public function getMountPoint()
 {
 }

	public function getOwner()
 {
 }

	public function getChecksum() {
	}

	public function getExtension(): string
 {
 }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @throws LockedException
	 */
	public function lock($type)
 {
 }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @throws LockedException
	 */
	public function changeLock($type)
 {
 }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @throws LockedException
	 */
	public function unlock($type)
 {
 }

	/**
	 * @param string $targetPath
	 * @return INode
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException if copy not allowed or failed
	 */
	public function copy($targetPath)
 {
 }

	/**
	 * @param string $targetPath
	 * @return INode
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException if move not allowed or failed
	 * @throws LockedException
	 */
	public function move($targetPath)
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

	/**
	 * @inheritDoc
	 * @return array<string, int|string|bool|float|string[]|int[]>
	 */
	public function getMetadata(): array
 {
 }
}
