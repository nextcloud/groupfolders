<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files;

use OC\Files\Mount\MountPoint;
use OC\User\NoUserException;
use OCP\Cache\CappedMemoryCache;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\FilesystemTornDownEvent;
use OCP\Files\Mount\IMountManager;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class Filesystem {
	public static bool $loaded = false;

	/**
	 * classname which used for hooks handling
	 * used as signalclass in OC_Hooks::emit()
	 */
	public const CLASSNAME = 'OC_Filesystem';

	/**
	 * signalname emitted before file renaming
	 *
	 * @param string $oldpath
	 * @param string $newpath
	 */
	public const signal_rename = 'rename';

	/**
	 * signal emitted after file renaming
	 *
	 * @param string $oldpath
	 * @param string $newpath
	 */
	public const signal_post_rename = 'post_rename';

	/**
	 * signal emitted before file/dir creation
	 *
	 * @param string $path
	 * @param bool $run changing this flag to false in hook handler will cancel event
	 */
	public const signal_create = 'create';

	/**
	 * signal emitted after file/dir creation
	 *
	 * @param string $path
	 * @param bool $run changing this flag to false in hook handler will cancel event
	 */
	public const signal_post_create = 'post_create';

	/**
	 * signal emits before file/dir copy
	 *
	 * @param string $oldpath
	 * @param string $newpath
	 * @param bool $run changing this flag to false in hook handler will cancel event
	 */
	public const signal_copy = 'copy';

	/**
	 * signal emits after file/dir copy
	 *
	 * @param string $oldpath
	 * @param string $newpath
	 */
	public const signal_post_copy = 'post_copy';

	/**
	 * signal emits before file/dir save
	 *
	 * @param string $path
	 * @param bool $run changing this flag to false in hook handler will cancel event
	 */
	public const signal_write = 'write';

	/**
	 * signal emits after file/dir save
	 *
	 * @param string $path
	 */
	public const signal_post_write = 'post_write';

	/**
	 * signal emitted before file/dir update
	 *
	 * @param string $path
	 * @param bool $run changing this flag to false in hook handler will cancel event
	 */
	public const signal_update = 'update';

	/**
	 * signal emitted after file/dir update
	 *
	 * @param string $path
	 * @param bool $run changing this flag to false in hook handler will cancel event
	 */
	public const signal_post_update = 'post_update';

	/**
	 * signal emits when reading file/dir
	 *
	 * @param string $path
	 */
	public const signal_read = 'read';

	/**
	 * signal emits when removing file/dir
	 *
	 * @param string $path
	 */
	public const signal_delete = 'delete';

	/**
	 * parameters definitions for signals
	 */
	public const signal_param_path = 'path';
	public const signal_param_oldpath = 'oldpath';
	public const signal_param_newpath = 'newpath';

	/**
	 * run - changing this flag to false in hook handler will cancel event
	 */
	public const signal_param_run = 'run';

	public const signal_create_mount = 'create_mount';
	public const signal_delete_mount = 'delete_mount';
	public const signal_param_mount_type = 'mounttype';
	public const signal_param_users = 'users';

	/**
	 * @param bool $shouldLog
	 * @return bool previous value
	 * @internal
	 */
	public static function logWarningWhenAddingStorageWrapper(bool $shouldLog): bool
 {
 }

	/**
	 * @param string $wrapperName
	 * @param callable $wrapper
	 * @param int $priority
	 */
	public static function addStorageWrapper($wrapperName, $wrapper, $priority = 50)
 {
 }

	/**
	 * Returns the storage factory
	 *
	 * @return IStorageFactory
	 */
	public static function getLoader()
 {
 }

	/**
	 * Returns the mount manager
	 */
	public static function getMountManager(): Mount\Manager
 {
 }

	/**
	 * get the mountpoint of the storage object for a path
	 * ( note: because a storage is not always mounted inside the fakeroot, the
	 * returned mountpoint is relative to the absolute root of the filesystem
	 * and doesn't take the chroot into account )
	 *
	 * @param string $path
	 * @return string
	 */
	public static function getMountPoint($path)
 {
 }

	/**
	 * get a list of all mount points in a directory
	 *
	 * @param string $path
	 * @return string[]
	 */
	public static function getMountPoints($path)
 {
 }

	/**
	 * get the storage mounted at $mountPoint
	 *
	 * @param string $mountPoint
	 * @return \OC\Files\Storage\Storage|null
	 */
	public static function getStorage($mountPoint)
 {
 }

	/**
	 * @param string $id
	 * @return Mount\MountPoint[]
	 */
	public static function getMountByStorageId($id)
 {
 }

	/**
	 * @param int $id
	 * @return Mount\MountPoint[]
	 */
	public static function getMountByNumericId($id)
 {
 }

	/**
	 * resolve a path to a storage and internal path
	 *
	 * @param string $path
	 * @return array{?\OCP\Files\Storage\IStorage, string} an array consisting of the storage and the internal path
	 */
	public static function resolvePath($path): array
 {
 }

	public static function init(string|IUser|null $user, string $root): bool
 {
 }

	public static function initInternal(string $root): bool
 {
 }

	public static function initMountManager(): void
 {
 }

	/**
	 * Initialize system and personal mount points for a user
	 *
	 * @throws \OC\User\NoUserException if the user is not available
	 */
	public static function initMountPoints(string|IUser|null $user = ''): void
 {
 }

	/**
	 * Get the default filesystem view
	 */
	public static function getView(): ?View
 {
 }

	/**
	 * tear down the filesystem, removing all storage providers
	 */
	public static function tearDown()
 {
 }

	/**
	 * get the relative path of the root data directory for the current user
	 *
	 * @return ?string
	 *
	 * Returns path like /admin/files
	 */
	public static function getRoot()
 {
 }

	/**
	 * mount an \OC\Files\Storage\Storage in our virtual filesystem
	 *
	 * @param \OC\Files\Storage\Storage|string $class
	 * @param array $arguments
	 * @param string $mountpoint
	 */
	public static function mount($class, $arguments, $mountpoint)
 {
 }

	/**
	 * return the path to a local version of the file
	 * we need this because we can't know if a file is stored local or not from
	 * outside the filestorage and for some purposes a local file is needed
	 */
	public static function getLocalFile(string $path): string|false
 {
 }

	/**
	 * return path to file which reflects one visible in browser
	 *
	 * @param string $path
	 * @return string
	 */
	public static function getLocalPath($path)
 {
 }

	/**
	 * check if the requested path is valid
	 *
	 * @param string $path
	 * @return bool
	 */
	public static function isValidPath($path)
 {
 }

	/**
	 * @param string $filename
	 * @return bool
	 */
	public static function isFileBlacklisted($filename)
 {
 }

	/**
	 * check if the directory should be ignored when scanning
	 * NOTE: the special directories . and .. would cause never ending recursion
	 *
	 * @param string $dir
	 * @return boolean
	 */
	public static function isIgnoredDir($dir)
 {
 }

	/**
	 * following functions are equivalent to their php builtin equivalents for arguments/return values.
	 */
	public static function mkdir($path)
 {
 }

	public static function rmdir($path)
 {
 }

	public static function is_dir($path)
 {
 }

	public static function is_file($path)
 {
 }

	public static function stat($path)
 {
 }

	public static function filetype($path)
 {
 }

	public static function filesize($path)
 {
 }

	public static function readfile($path)
 {
 }

	public static function isCreatable($path)
 {
 }

	public static function isReadable($path)
 {
 }

	public static function isUpdatable($path)
 {
 }

	public static function isDeletable($path)
 {
 }

	public static function isSharable($path)
 {
 }

	public static function file_exists($path)
 {
 }

	public static function filemtime($path)
 {
 }

	public static function touch($path, $mtime = null)
 {
 }

	/**
	 * @return string|false
	 */
	public static function file_get_contents($path)
 {
 }

	public static function file_put_contents($path, $data)
 {
 }

	public static function unlink($path)
 {
 }

	public static function rename($source, $target)
 {
 }

	public static function copy($source, $target)
 {
 }

	public static function fopen($path, $mode)
 {
 }

	/**
	 * @param string $path
	 * @throws \OCP\Files\InvalidPathException
	 */
	public static function toTmpFile($path): string|false
 {
 }

	public static function fromTmpFile($tmpFile, $path)
 {
 }

	public static function getMimeType($path)
 {
 }

	public static function hash($type, $path, $raw = false)
 {
 }

	public static function free_space($path = '/')
 {
 }

	public static function search($query)
 {
 }

	/**
	 * @param string $query
	 */
	public static function searchByMime($query)
 {
 }

	/**
	 * @param string|int $tag name or tag id
	 * @param string $userId owner of the tags
	 * @return FileInfo[] array or file info
	 */
	public static function searchByTag($tag, $userId)
 {
 }

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public static function hasUpdated($path, $time)
 {
 }

	/**
	 * Fix common problems with a file path
	 *
	 * @param string $path
	 * @param bool $stripTrailingSlash whether to strip the trailing slash
	 * @param bool $isAbsolutePath whether the given path is absolute
	 * @param bool $keepUnicode true to disable unicode normalization
	 * @psalm-taint-escape file
	 * @return string
	 */
	public static function normalizePath($path, $stripTrailingSlash = true, $isAbsolutePath = false, $keepUnicode = false)
 {
 }

	/**
	 * get the filesystem info
	 *
	 * @param string $path
	 * @param bool|string $includeMountPoints whether to add mountpoint sizes,
	 *                                        defaults to true
	 * @return \OC\Files\FileInfo|false False if file does not exist
	 */
	public static function getFileInfo($path, $includeMountPoints = true)
 {
 }

	/**
	 * change file metadata
	 *
	 * @param string $path
	 * @param array $data
	 * @return int
	 *
	 * returns the fileid of the updated file
	 */
	public static function putFileInfo($path, $data)
 {
 }

	/**
	 * get the content of a directory
	 *
	 * @param string $directory path under datadirectory
	 * @param string $mimetype_filter limit returned content to this mimetype or mimepart
	 * @return \OC\Files\FileInfo[]
	 */
	public static function getDirectoryContent($directory, $mimetype_filter = '')
 {
 }

	/**
	 * Get the path of a file by id
	 *
	 * Note that the resulting path is not guaranteed to be unique for the id, multiple paths can point to the same file
	 *
	 * @param int $id
	 * @throws NotFoundException
	 * @return string
	 */
	public static function getPath($id)
 {
 }

	/**
	 * Get the owner for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public static function getOwner($path)
 {
 }

	/**
	 * get the ETag for a file or folder
	 */
	public static function getETag(string $path): string|false
 {
 }
}
