<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Node;

use OC\Files\Filesystem;
use OC\Files\Utils\PathHelper;
use OC\Files\View;
use OCP\Constants;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMovableMount;
use OCP\Files\Node as INode;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;
use OCP\Server;

// FIXME: this class really should be abstract (+1)
class Node implements INode {
	/**
	 * @var View $view
	 */
	protected $view;

	protected IRootFolder $root;

	/**
	 * @param View $view
	 * @param \OCP\Files\IRootFolder $root
	 * @param string $path
	 * @param FileInfo $fileInfo
	 */
	public function __construct(IRootFolder $root, $view, protected $path, protected ?FileInfo $fileInfo = null, protected ?INode $parent = null, private bool $infoHasSubMountsIncluded = true)
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

	#[\Override]
	public function delete() {
	}

	/**
	 * @param int $mtime
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	#[\Override]
    public function touch($mtime = null)
    {
    }

	#[\Override]
    public function getStorage()
    {
    }

	/**
	 * @return string
	 */
	#[\Override]
    public function getPath()
    {
    }

	/**
	 * @return string
	 */
	#[\Override]
    public function getInternalPath()
    {
    }

	/**
	 * @return int
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function getId()
    {
    }

	/**
	 * @return array
	 */
	#[\Override]
    public function stat()
    {
    }

	/**
	 * @return int
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function getMTime()
    {
    }

	/**
	 * @param bool $includeMounts
	 * @return int|float
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function getSize($includeMounts = true): int|float
    {
    }

	/**
	 * @return string
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function getEtag()
    {
    }

	/**
	 * @return int
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function getPermissions()
    {
    }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function isReadable()
    {
    }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function isUpdateable()
    {
    }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function isDeletable()
    {
    }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function isShareable()
    {
    }

	/**
	 * @return bool
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	#[\Override]
    public function isCreatable()
    {
    }

	#[\Override]
    public function getParent(): INode|IRootFolder
    {
    }

	/**
	 * @return string
	 */
	#[\Override]
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

	#[\Override]
    public function isMounted()
    {
    }

	#[\Override]
    public function isShared()
    {
    }

	#[\Override]
    public function getMimeType(): string
    {
    }

	#[\Override]
    public function getMimePart()
    {
    }

	#[\Override]
    public function getType()
    {
    }

	#[\Override]
    public function isEncrypted()
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
	public function getChecksum() {
	}

	#[\Override]
    public function getExtension(): string
    {
    }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @throws LockedException
	 */
	#[\Override]
    public function lock($type)
    {
    }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @throws LockedException
	 */
	#[\Override]
    public function changeLock($type)
    {
    }

	/**
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @throws LockedException
	 */
	#[\Override]
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
	#[\Override]
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
	#[\Override]
    public function move($targetPath)
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
