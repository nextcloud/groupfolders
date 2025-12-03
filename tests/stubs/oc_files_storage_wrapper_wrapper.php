<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Storage\FailedStorage;
use OCP\Files\InvalidPathException;
use OCP\Files\Storage\ILockingStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\Lock\ILockingProvider;
use OCP\Server;
use Psr\Log\LoggerInterface;

class Wrapper implements \OC\Files\Storage\Storage, ILockingStorage, IWriteStreamStorage {
	/**
	 * @var \OC\Files\Storage\Storage $storage
	 */
	protected $storage;

	public $cache;
	public $scanner;
	public $watcher;
	public $propagator;
	public $updater;

	/**
	 * @param array $parameters
	 */
	public function __construct($parameters)
 {
 }

	/**
	 * @return \OC\Files\Storage\Storage
	 */
	public function getWrapperStorage()
 {
 }

	/**
	 * Get the identifier for the storage,
	 * the returned id should be the same for every storage object that is created with the same parameters
	 * and two storage objects with the same id should refer to two storages that display the same files.
	 *
	 * @return string
	 */
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
	 * @return string|bool
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
	 * search for occurrences of $query in file names
	 *
	 * @param string $query
	 * @return array|bool
	 */
	public function search($query)
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

	/**
	 * get a cache instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage|null (optional) the storage to pass to the cache
	 * @return \OC\Files\Cache\Cache
	 */
	public function getCache($path = '', $storage = null)
 {
 }

	/**
	 * get a scanner instance for the storage
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the scanner
	 * @return \OC\Files\Cache\Scanner
	 */
	public function getScanner($path = '', $storage = null)
 {
 }


	/**
	 * get the user id of the owner of a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getOwner($path)
 {
 }

	/**
	 * get a watcher instance for the cache
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the watcher
	 * @return \OC\Files\Cache\Watcher
	 */
	public function getWatcher($path = '', $storage = null)
 {
 }

	public function getPropagator($storage = null)
 {
 }

	public function getUpdater($storage = null)
 {
 }

	/**
	 * @return \OC\Files\Cache\Storage
	 */
	public function getStorageCache()
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
	 * Returns true
	 *
	 * @return true
	 */
	public function test()
 {
 }

	/**
	 * Returns the wrapped storage's value for isLocal()
	 *
	 * @return bool wrapped storage's isLocal() value
	 */
	public function isLocal()
 {
 }

	/**
	 * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
	 *
	 * @param class-string<IStorage> $class
	 * @return bool
	 */
	public function instanceOfStorage($class)
 {
 }

	/**
	 * @psalm-template T of IStorage
	 * @psalm-param class-string<T> $class
	 * @psalm-return T|null
	 */
	public function getInstanceOfStorage(string $class)
 {
 }

	/**
	 * Pass any methods custom to specific storage implementations to the wrapped storage
	 *
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call($method, $args)
 {
 }

	/**
	 * A custom storage implementation can return an url for direct download of a give file.
	 *
	 * For now the returned array can hold the parameter url - in future more attributes might follow.
	 *
	 * @param string $path
	 * @return array|bool
	 */
	public function getDirectDownload($path)
 {
 }

	/**
	 * Get availability of the storage
	 *
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability()
 {
 }

	/**
	 * Set availability of the storage
	 *
	 * @param bool $isAvailable
	 */
	public function setAvailability($isAvailable)
 {
 }

	/**
	 * @param string $path the path of the target folder
	 * @param string $fileName the name of the file itself
	 * @return void
	 * @throws InvalidPathException
	 */
	public function verifyPath($path, $fileName)
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

	public function getMetaData($path)
 {
 }

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function acquireLock($path, $type, ILockingProvider $provider)
 {
 }

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function releaseLock($path, $type, ILockingProvider $provider)
 {
 }

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 */
	public function changeLock($path, $type, ILockingProvider $provider)
 {
 }

	/**
	 * @return bool
	 */
	public function needsPartFile()
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getDirectoryContent($directory): \Traversable
 {
 }

	public function isWrapperOf(IStorage $storage)
 {
 }

	public function setOwner(?string $user): void
 {
 }
}
