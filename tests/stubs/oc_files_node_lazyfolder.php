<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Files\Node;

use OC\Files\Filesystem;
use OC\Files\Utils\PathHelper;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Node;
use OCP\Files\NotPermittedException;
use Override;

/**
 * Class LazyFolder
 *
 * This is a lazy wrapper around a folder. So only
 * once it is needed this will get initialized.
 *
 * @package OC\Files\Node
 */
class LazyFolder implements Folder {
	protected ?Folder $folder = null;
	protected IRootFolder $rootFolder;

	/**
	 * @param IRootFolder $rootFolder
	 * @param \Closure(): Folder $folderClosure
	 * @param array $data
	 */
	public function __construct(IRootFolder $rootFolder, private \Closure $folderClosure, protected array $data = [])
    {
    }

	protected function getRootFolder(): IRootFolder
    {
    }

	protected function getRealFolder(): Folder
    {
    }

	/**
	 * Magic method to first get the real rootFolder and then
	 * call $method with $args on it
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public function __call($method, $args)
    {
    }

	/**
	 * @inheritDoc
	 */
	public function getUser()
    {
    }

	/**
	 * @inheritDoc
	 */
	public function listen($scope, $method, callable $callback)
    {
    }

	/**
	 * @inheritDoc
	 */
	public function removeListener($scope = null, $method = null, ?callable $callback = null)
    {
    }

	/**
	 * @inheritDoc
	 */
	public function emit($scope, $method, $arguments = [])
    {
    }

	/**
	 * @inheritDoc
	 */
	public function mount($storage, $mountPoint, $arguments = [])
    {
    }

	/**
	 * @inheritDoc
	 */
	public function getMount(string $mountPoint): IMountPoint
    {
    }

	/**
	 * @return IMountPoint[]
	 */
	public function getMountsIn(string $mountPoint): array
    {
    }

	/**
	 * @inheritDoc
	 */
	public function getMountByStorageId($storageId)
    {
    }

	/**
	 * @inheritDoc
	 */
	public function getMountByNumericStorageId($numericId)
    {
    }

	/**
	 * @inheritDoc
	 */
	public function unMount($mount)
    {
    }

	#[\Override]
    public function get($path)
    {
    }

	#[Override]
    public function getOrCreateFolder(string $path, int $maxRetries = 5): Folder
    {
    }

	/**
	 * @inheritDoc
	 */
	public function rename($targetPath)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function delete()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function copy($targetPath)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function touch($mtime = null)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getStorage()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getPath()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getInternalPath()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getId()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function stat()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getMTime()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getSize($includeMounts = true): int|float
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getEtag()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getPermissions()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isReadable()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isUpdateable()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isDeletable()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isShareable()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getParent()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getName()
    {
    }

	/**
	 * @inheritDoc
	 */
	public function getUserFolder($userId)
    {
    }

	#[\Override]
    public function getMimetype(): string
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getMimePart()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isEncrypted()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getType()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isShared()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isMounted()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getMountPoint()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getOwner()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getChecksum()
    {
    }

	#[\Override]
    public function getExtension(): string
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getFullPath($path)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isSubNode($node)
    {
    }

	#[Override]
    public function getDirectoryListing(?string $mimetypeFilter = null): array
    {
    }

	#[\Override]
    public function nodeExists($path)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function newFolder($path)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function newFile($path, $content = null)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function search($query)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function searchByMime($mimetype)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function searchByTag($tag, $userId)
    {
    }

	#[\Override]
    public function searchBySystemTag(string $tagName, string $userId, int $limit = 0, int $offset = 0)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getById($id)
    {
    }

	#[\Override]
    public function getFirstNodeById(int $id): ?Node
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getFreeSpace()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function isCreatable()
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getNonExistingName($filename)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function move($targetPath)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function lock($type)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function changeLock($targetType)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function unlock($type)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getRecent($limit, $offset = 0)
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getCreationTime(): int
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getUploadTime(): int
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getLastActivity(): int
    {
    }

	#[\Override]
    public function getRelativePath($path)
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

	#[\Override]
    public function verifyPath($fileName, $readonly = false): void
    {
    }
}
