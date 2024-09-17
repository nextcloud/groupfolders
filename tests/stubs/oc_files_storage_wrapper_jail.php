<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Cache\Wrapper\CacheJail;
use OC\Files\Cache\Wrapper\JailPropagator;
use OC\Files\Cache\Wrapper\JailWatcher;
use OC\Files\Filesystem;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\Lock\ILockingProvider;

/**
 * Jail to a subdirectory of the wrapped storage
 *
 * This restricts access to a subfolder of the wrapped storage with the subfolder becoming the root folder new storage
 */
class Jail extends Wrapper {
	/**
	 * @var string
	 */
	protected $rootPath;

	/**
	 * @param array $arguments ['storage' => $storage, 'root' => $root]
	 *
	 * $storage: The storage that will be wrapper
	 * $root: The folder in the wrapped storage that will become the root folder of the wrapped storage
	 */
	public function __construct($arguments)
 {
 }

	public function getUnjailedPath($path)
 {
 }

	/**
	 * This is separate from Wrapper::getWrapperStorage so we can get the jailed storage consistently even if the jail is inside another wrapper
	 */
	public function getUnjailedStorage()
 {
 }


	public function getJailedPath($path)
 {
 }

	public function getId()
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.mkdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function mkdir($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.rmdir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function rmdir($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.opendir.php
	 *
	 * @param string $path
	 * @return resource|false
	 */
	public function opendir($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.is_dir.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_dir($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.is_file.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function is_file($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.stat.php
	 * only the following keys are required in the result: size and mtime
	 *
	 * @param string $path
	 * @return array|bool
	 */
	public function stat($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.filetype.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function filetype($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.filesize.php
	 * The result for filesize when called on a folder is required to be 0
	 */
	public function filesize($path): false|int|float
 {
 }

	/**
	 * check if a file can be created in $path
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isCreatable($path)
 {
 }

	/**
	 * check if a file can be read
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isReadable($path)
 {
 }

	/**
	 * check if a file can be written to
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isUpdatable($path)
 {
 }

	/**
	 * check if a file can be deleted
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isDeletable($path)
 {
 }

	/**
	 * check if a file can be shared
	 *
	 * @param string $path
	 * @return bool
	 */
	public function isSharable($path)
 {
 }

	/**
	 * get the full permissions of a path.
	 * Should return a combination of the PERMISSION_ constants defined in lib/public/constants.php
	 *
	 * @param string $path
	 * @return int
	 */
	public function getPermissions($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.file_exists.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function file_exists($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.filemtime.php
	 *
	 * @param string $path
	 * @return int|bool
	 */
	public function filemtime($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.file_get_contents.php
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function file_get_contents($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param mixed $data
	 * @return int|float|false
	 */
	public function file_put_contents($path, $data)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.unlink.php
	 *
	 * @param string $path
	 * @return bool
	 */
	public function unlink($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.rename.php
	 *
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	public function rename($source, $target)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.copy.php
	 *
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	public function copy($source, $target)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|bool
	 */
	public function fopen($path, $mode)
 {
 }

	/**
	 * get the mimetype for a file or folder
	 * The mimetype for a folder is required to be "httpd/unix-directory"
	 *
	 * @param string $path
	 * @return string|bool
	 */
	public function getMimeType($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.hash.php
	 *
	 * @param string $type
	 * @param string $path
	 * @param bool $raw
	 * @return string|bool
	 */
	public function hash($type, $path, $raw = false)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.free_space.php
	 *
	 * @param string $path
	 * @return int|float|bool
	 */
	public function free_space($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.touch.php
	 * If the backend does not support the operation, false should be returned
	 *
	 * @param string $path
	 * @param int $mtime
	 * @return bool
	 */
	public function touch($path, $mtime = null)
 {
 }

	/**
	 * get the path to a local version of the file.
	 * The local version of the file can be temporary and doesn't have to be persistent across requests
	 *
	 * @param string $path
	 * @return string|false
	 */
	public function getLocalFile($path)
 {
 }

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 *
	 * hasUpdated for folders should return at least true if a file inside the folder is add, removed or renamed.
	 * returning true for other changes in the folder is optional
	 */
	public function hasUpdated($path, $time)
 {
 }

	public function getCache($path = '', $storage = null)
 {
 }

	public function getOwner($path): string|false
 {
 }

	public function getWatcher($path = '', $storage = null)
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

	public function getMetaData($path)
 {
 }

	public function acquireLock($path, $type, ILockingProvider $provider)
 {
 }

	public function releaseLock($path, $type, ILockingProvider $provider)
 {
 }

	public function changeLock($path, $type, ILockingProvider $provider)
 {
 }

	/**
	 * Resolve the path for the source of the share
	 *
	 * @param string $path
	 * @return array
	 */
	public function resolvePath($path)
 {
 }

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath)
 {
 }

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath)
 {
 }

	public function getPropagator($storage = null)
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getDirectoryContent($directory): \Traversable
 {
 }
}
