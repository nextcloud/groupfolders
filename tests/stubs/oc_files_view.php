<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files;

use Icewind\Streams\CallbackWrapper;
use OC\Files\Mount\MoveableMount;
use OC\Files\Storage\Storage;
use OC\Share\Share;
use OC\User\LazyUser;
use OC\User\Manager as UserManager;
use OC\User\User;
use OCA\Files_Sharing\SharedMount;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\ConnectionLostException;
use OCP\Files\EmptyFileNameException;
use OCP\Files\FileNameTooLongException;
use OCP\Files\ForbiddenException;
use OCP\Files\InvalidCharacterInPathException;
use OCP\Files\InvalidDirectoryException;
use OCP\Files\InvalidPathException;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\ReservedWordException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Server;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Class to provide access to ownCloud filesystem via a "view", and methods for
 * working with files within that view (e.g. read, write, delete, etc.). Each
 * view is restricted to a set of directories via a virtual root. The default view
 * uses the currently logged in user's data directory as root (parts of
 * OC_Filesystem are merely a wrapper for OC\Files\View).
 *
 * Apps that need to access files outside of the user data folders (to modify files
 * belonging to a user other than the one currently logged in, for example) should
 * use this class directly rather than using OC_Filesystem, or making use of PHP's
 * built-in file manipulation functions. This will ensure all hooks and proxies
 * are triggered correctly.
 *
 * Filesystem functions are not called directly; they are passed to the correct
 * \OC\Files\Storage\Storage object
 */
class View {
	/**
	 * @throws \Exception If $root contains an invalid path
	 */
	public function __construct(string $root = '')
 {
 }

	/**
	 * @param ?string $path
	 * @psalm-template S as string|null
	 * @psalm-param S $path
	 * @psalm-return (S is string ? string : null)
	 */
	public function getAbsolutePath($path = '/'): ?string
 {
 }

	/**
	 * Change the root to a fake root
	 *
	 * @param string $fakeRoot
	 */
	public function chroot($fakeRoot): void
 {
 }

	/**
	 * Get the fake root
	 */
	public function getRoot(): string
 {
 }

	/**
	 * get path relative to the root of the view
	 *
	 * @param string $path
	 */
	public function getRelativePath($path): ?string
 {
 }

	/**
	 * Get the mountpoint of the storage object for a path
	 * ( note: because a storage is not always mounted inside the fakeroot, the
	 * returned mountpoint is relative to the absolute root of the filesystem
	 * and does not take the chroot into account )
	 *
	 * @param string $path
	 */
	public function getMountPoint($path): string
 {
 }

	/**
	 * Get the mountpoint of the storage object for a path
	 * ( note: because a storage is not always mounted inside the fakeroot, the
	 * returned mountpoint is relative to the absolute root of the filesystem
	 * and does not take the chroot into account )
	 *
	 * @param string $path
	 */
	public function getMount($path): IMountPoint
 {
 }

	/**
	 * Resolve a path to a storage and internal path
	 *
	 * @param string $path
	 * @return array{?\OCP\Files\Storage\IStorage, string} an array consisting of the storage and the internal path
	 */
	public function resolvePath($path): array
 {
 }

	/**
	 * Return the path to a local version of the file
	 * we need this because we can't know if a file is stored local or not from
	 * outside the filestorage and for some purposes a local file is needed
	 *
	 * @param string $path
	 */
	public function getLocalFile($path): string|false
 {
 }

	/**
	 * the following functions operate with arguments and return values identical
	 * to those of their PHP built-in equivalents. Mostly they are merely wrappers
	 * for \OC\Files\Storage\Storage via basicOperation().
	 */
	public function mkdir($path)
 {
 }

	/**
	 * remove mount point
	 *
	 * @param IMountPoint $mount
	 * @param string $path relative to data/
	 */
	protected function removeMount($mount, $path): bool
 {
 }

	public function disableCacheUpdate(): void
 {
 }

	public function enableCacheUpdate(): void
 {
 }

	protected function writeUpdate(Storage $storage, string $internalPath, ?int $time = null, ?int $sizeDifference = null): void
 {
 }

	protected function removeUpdate(Storage $storage, string $internalPath): void
 {
 }

	protected function renameUpdate(Storage $sourceStorage, Storage $targetStorage, string $sourceInternalPath, string $targetInternalPath): void
 {
 }

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function rmdir($path)
 {
 }

	/**
	 * @param string $path
	 * @return resource|false
	 */
	public function opendir($path)
 {
 }

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function is_dir($path)
 {
 }

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function is_file($path)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function stat($path)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function filetype($path)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function filesize(string $path)
 {
 }

	/**
	 * @param string $path
	 * @return bool|mixed
	 * @throws InvalidPathException
	 */
	public function readfile($path)
 {
 }

	/**
	 * @param string $path
	 * @param int $from
	 * @param int $to
	 * @return bool|mixed
	 * @throws InvalidPathException
	 * @throws \OCP\Files\UnseekableException
	 */
	public function readfilePart($path, $from, $to)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isCreatable($path)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isReadable($path)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isUpdatable($path)
 {
 }

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function isDeletable($path)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function isSharable($path)
 {
 }

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function file_exists($path)
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 */
	public function filemtime($path)
 {
 }

	/**
	 * @param string $path
	 * @param int|string $mtime
	 */
	public function touch($path, $mtime = null): bool
 {
 }

	/**
	 * @param string $path
	 * @return string|false
	 * @throws LockedException
	 */
	public function file_get_contents($path)
 {
 }

	protected function emit_file_hooks_pre(bool $exists, string $path, bool &$run): void
 {
 }

	protected function emit_file_hooks_post(bool $exists, string $path): void
 {
 }

	/**
	 * @param string $path
	 * @param string|resource $data
	 * @return bool|mixed
	 * @throws LockedException
	 */
	public function file_put_contents($path, $data)
 {
 }

	/**
	 * @param string $path
	 * @return bool|mixed
	 */
	public function unlink($path)
 {
 }

	/**
	 * @param string $directory
	 * @return bool|mixed
	 */
	public function deleteAll($directory)
 {
 }

	/**
	 * Rename/move a file or folder from the source path to target path.
	 *
	 * @param string $source source path
	 * @param string $target target path
	 *
	 * @return bool|mixed
	 * @throws LockedException
	 */
	public function rename($source, $target)
 {
 }

	/**
	 * Copy a file/folder from the source path to target path
	 *
	 * @param string $source source path
	 * @param string $target target path
	 * @param bool $preserveMtime whether to preserve mtime on the copy
	 *
	 * @return bool|mixed
	 */
	public function copy($source, $target, $preserveMtime = false)
 {
 }

	/**
	 * @param string $path
	 * @param string $mode 'r' or 'w'
	 * @return resource|false
	 * @throws LockedException
	 */
	public function fopen($path, $mode)
 {
 }

	/**
	 * @param string $path
	 * @throws InvalidPathException
	 */
	public function toTmpFile($path): string|false
 {
 }

	/**
	 * @param string $tmpFile
	 * @param string $path
	 * @return bool|mixed
	 * @throws InvalidPathException
	 */
	public function fromTmpFile($tmpFile, $path)
 {
 }


	/**
	 * @param string $path
	 * @return mixed
	 * @throws InvalidPathException
	 */
	public function getMimeType($path)
 {
 }

	/**
	 * @param string $type
	 * @param string $path
	 * @param bool $raw
	 */
	public function hash($type, $path, $raw = false): string|bool
 {
 }

	/**
	 * @param string $path
	 * @return mixed
	 * @throws InvalidPathException
	 */
	public function free_space($path = '/')
 {
 }

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time)
 {
 }

	/**
	 * get the filesystem info
	 *
	 * @param string $path
	 * @param bool|string $includeMountPoints true to add mountpoint sizes,
	 *                                        'ext' to add only ext storage mount point sizes. Defaults to true.
	 * @return \OC\Files\FileInfo|false False if file does not exist
	 */
	public function getFileInfo($path, $includeMountPoints = true)
 {
 }

	/**
	 * Extend a FileInfo that was previously requested with `$includeMountPoints = false` to include the sub mounts
	 */
	public function addSubMounts(FileInfo $info, $extOnly = false): void
 {
 }

	/**
	 * get the content of a directory
	 *
	 * @param string $directory path under datadirectory
	 * @param string $mimetype_filter limit returned content to this mimetype or mimepart
	 * @return FileInfo[]
	 */
	public function getDirectoryContent($directory, $mimetype_filter = '', ?\OCP\Files\FileInfo $directoryInfo = null)
 {
 }

	/**
	 * change file metadata
	 *
	 * @param string $path
	 * @param array|\OCP\Files\FileInfo $data
	 * @return int
	 *
	 * returns the fileid of the updated file
	 */
	public function putFileInfo($path, $data)
 {
 }

	/**
	 * search for files with the name matching $query
	 *
	 * @param string $query
	 * @return FileInfo[]
	 */
	public function search($query)
 {
 }

	/**
	 * search for files with the name matching $query
	 *
	 * @param string $query
	 * @return FileInfo[]
	 */
	public function searchRaw($query)
 {
 }

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return FileInfo[]
	 */
	public function searchByMime($mimetype)
 {
 }

	/**
	 * search for files by tag
	 *
	 * @param string|int $tag name or tag id
	 * @param string $userId owner of the tags
	 * @return FileInfo[]
	 */
	public function searchByTag($tag, $userId)
 {
 }

	/**
	 * Get the owner for a file or folder
	 *
	 * @throws NotFoundException
	 */
	public function getOwner(string $path): string
 {
 }

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function getETag($path)
 {
 }

	/**
	 * Get the path of a file by id, relative to the view
	 *
	 * Note that the resulting path is not guaranteed to be unique for the id, multiple paths can point to the same file
	 *
	 * @param int $id
	 * @param int|null $storageId
	 * @return string
	 * @throws NotFoundException
	 */
	public function getPath($id, ?int $storageId = null)
 {
 }

	/**
	 * @param string $path
	 * @param string $fileName
	 * @param bool $readonly Check only if the path is allowed for read-only access
	 * @throws InvalidPathException
	 */
	public function verifyPath($path, $fileName, $readonly = false): void
 {
 }

	/**
	 * Change the lock type
	 *
	 * @param string $path the path of the file to lock, relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException if the path is already locked
	 */
	public function changeLock($path, $type, $lockMountPoint = false)
 {
 }

	/**
	 * Lock a path and all its parents up to the root of the view
	 *
	 * @param string $path the path of the file to lock relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException
	 */
	public function lockFile($path, $type, $lockMountPoint = false)
 {
 }

	/**
	 * Unlock a path and all its parents up to the root of the view
	 *
	 * @param string $path the path of the file to lock relative to the view
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param bool $lockMountPoint true to lock the mount point, false to lock the attached mount/storage
	 *
	 * @return bool False if the path is excluded from locking, true otherwise
	 * @throws LockedException
	 */
	public function unlockFile($path, $type, $lockMountPoint = false)
 {
 }

	/**
	 * Only lock files in data/user/files/
	 *
	 * @param string $path Absolute path to the file/folder we try to (un)lock
	 * @return bool
	 */
	protected function shouldLockFile($path)
 {
 }

	/**
	 * Shortens the given absolute path to be relative to
	 * "$user/files".
	 *
	 * @param string $absolutePath absolute path which is under "files"
	 *
	 * @return string path relative to "files" with trimmed slashes or null
	 *                if the path was NOT relative to files
	 *
	 * @throws \InvalidArgumentException if the given path was not under "files"
	 * @since 8.1.0
	 */
	public function getPathRelativeToFiles($absolutePath)
 {
 }

	/**
	 * @param string $filename
	 * @return array
	 * @throws \OC\User\NoUserException
	 * @throws NotFoundException
	 */
	public function getUidAndFilename($filename)
 {
 }
}
