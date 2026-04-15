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
	public function delete()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function copy($targetPath)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function touch($mtime = null)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getStorage()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getPath()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getInternalPath()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getId()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function stat()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getMTime()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getSize($includeMounts = true): int|float
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getEtag()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getPermissions()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isReadable()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isUpdateable()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isDeletable()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isShareable()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getParent()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getName()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getUserFolder($userId)
 {
 }

	public function getMimetype(): string
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getMimePart()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isEncrypted()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getType()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isShared()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isMounted()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getMountPoint()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getOwner()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getChecksum()
 {
 }

	public function getExtension(): string
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getFullPath($path)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isSubNode($node)
 {
 }

	#[Override]
 public function getDirectoryListing(?string $mimetypeFilter = null): array
 {
 }

	public function nodeExists($path)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function newFolder($path)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function newFile($path, $content = null)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function search($query)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function searchByMime($mimetype)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function searchByTag($tag, $userId)
 {
 }

	public function searchBySystemTag(string $tagName, string $userId, int $limit = 0, int $offset = 0)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getById($id)
 {
 }

	public function getFirstNodeById(int $id): ?Node
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getFreeSpace()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function isCreatable()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getNonExistingName($filename)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function move($targetPath)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function lock($type)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function changeLock($targetType)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function unlock($type)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getRecent($limit, $offset = 0)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getCreationTime(): int
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getUploadTime(): int
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getLastActivity(): int
 {
 }

	public function getRelativePath($path)
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

	public function verifyPath($fileName, $readonly = false): void
 {
 }
}
