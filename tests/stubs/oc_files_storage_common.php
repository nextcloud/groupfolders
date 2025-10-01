<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage;

use OC\Files\Cache\Cache;
use OC\Files\Cache\CacheDependencies;
use OC\Files\Cache\Propagator;
use OC\Files\Cache\Scanner;
use OC\Files\Cache\Updater;
use OC\Files\Cache\Watcher;
use OC\Files\FilenameValidator;
use OC\Files\Filesystem;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\ForbiddenException;
use OCP\Files\GenericFileException;
use OCP\Files\IFilenameValidator;
use OCP\Files\InvalidPathException;
use OCP\Files\Storage\ILockingStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Storage backend class for providing common filesystem operation methods
 * which are not storage-backend specific.
 *
 * \OC\Files\Storage\Common is never used directly; it is extended by all other
 * storage backends, where its methods may be overridden, and additional
 * (backend-specific) methods are defined.
 *
 * Some \OC\Files\Storage\Common methods call functions which are first defined
 * in classes which extend it, e.g. $this->stat() .
 */
abstract class Common implements Storage, ILockingStorage, IWriteStreamStorage {
	use LocalTempFileTrait;

	protected $cache;
	protected $scanner;
	protected $watcher;
	protected $propagator;
	protected $storageCache;
	protected $updater;

	protected $mountOptions = [];
	protected $owner = null;

	public function __construct($parameters) {
	}

	/**
	 * Remove a file or folder
	 *
	 * @param string $path
	 * @return bool
	 */
	protected function remove($path)
 {
 }

	public function is_dir($path)
 {
 }

	public function is_file($path)
 {
 }

	public function filesize($path): false|int|float
 {
 }

	public function isReadable($path)
 {
 }

	public function isUpdatable($path)
 {
 }

	public function isCreatable($path)
 {
 }

	public function isDeletable($path)
 {
 }

	public function isSharable($path)
 {
 }

	public function getPermissions($path)
 {
 }

	public function filemtime($path)
 {
 }

	public function file_get_contents($path)
 {
 }

	public function file_put_contents($path, $data)
 {
 }

	public function rename($source, $target)
 {
 }

	public function copy($source, $target)
 {
 }

	public function getMimeType($path)
 {
 }

	public function hash($type, $path, $raw = false)
 {
 }

	public function search($query)
 {
 }

	public function getLocalFile($path)
 {
 }

	/**
	 * @param string $query
	 * @param string $dir
	 * @return array
	 */
	protected function searchInDir($query, $dir = '')
 {
 }

	/**
	 * Check if a file or folder has been updated since $time
	 *
	 * The method is only used to check if the cache needs to be updated. Storage backends that don't support checking
	 * the mtime should always return false here. As a result storage implementations that always return false expect
	 * exclusive access to the backend and will not pick up files that have been added in a way that circumvents
	 * Nextcloud filesystem.
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time)
 {
 }

	protected function getCacheDependencies(): CacheDependencies
 {
 }

	public function getCache($path = '', $storage = null)
 {
 }

	public function getScanner($path = '', $storage = null)
 {
 }

	public function getWatcher($path = '', $storage = null)
 {
 }

	/**
	 * get a propagator instance for the cache
	 *
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the watcher
	 * @return \OC\Files\Cache\Propagator
	 */
	public function getPropagator($storage = null)
 {
 }

	public function getUpdater($storage = null)
 {
 }

	public function getStorageCache($storage = null)
 {
 }

	/**
	 * get the owner of a path
	 *
	 * @param string $path The path to get the owner
	 * @return string|false uid or false
	 */
	public function getOwner($path)
 {
 }

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getETag($path)
 {
 }

	/**
	 * clean a path, i.e. remove all redundant '.' and '..'
	 * making sure that it can't point to higher than '/'
	 *
	 * @param string $path The path to clean
	 * @return string cleaned path
	 */
	public function cleanPath($path)
 {
 }

	/**
	 * Test a storage for availability
	 *
	 * @return bool
	 */
	public function test()
 {
 }

	/**
	 * get the free space in the storage
	 *
	 * @param string $path
	 * @return int|float|false
	 */
	public function free_space($path)
 {
 }

	/**
	 * {@inheritdoc}
	 */
	public function isLocal()
 {
 }

	/**
	 * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
	 *
	 * @param string $class
	 * @return bool
	 */
	public function instanceOfStorage($class)
 {
 }

	/**
	 * A custom storage implementation can return an url for direct download of a give file.
	 *
	 * For now the returned array can hold the parameter url - in future more attributes might follow.
	 *
	 * @param string $path
	 * @return array|false
	 */
	public function getDirectDownload($path)
 {
 }

	/**
	 * @inheritdoc
	 * @throws InvalidPathException
	 */
	public function verifyPath($path, $fileName)
 {
 }

	/**
	 * Get the filename validator
	 * (cached for performance)
	 */
	protected function getFilenameValidator(): IFilenameValidator
 {
 }

	/**
	 * @param array $options
	 */
	public function setMountOptions(array $options)
 {
 }

	/**
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getMountOption($name, $default = null)
 {
 }

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 * @return bool
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false)
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

	/**
	 * @inheritdoc
	 */
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
	 * @throws \OCP\Lock\LockedException
	 */
	public function releaseLock($path, $type, ILockingProvider $provider)
 {
 }

	/**
	 * @param string $path
	 * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
	 * @param \OCP\Lock\ILockingProvider $provider
	 * @throws \OCP\Lock\LockedException
	 */
	public function changeLock($path, $type, ILockingProvider $provider)
 {
 }

	/**
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability()
 {
 }

	/**
	 * @param bool $isAvailable
	 */
	public function setAvailability($isAvailable)
 {
 }

	/**
	 * Allow setting the storage owner
	 *
	 * This can be used for storages that do not have a dedicated owner, where we want to
	 * pass the user that we setup the mountpoint for along to the storage layer
	 *
	 * @param string|null $user
	 * @return void
	 */
	public function setOwner(?string $user): void
 {
 }

	/**
	 * @return bool
	 */
	public function needsPartFile()
 {
 }

	/**
	 * fallback implementation
	 *
	 * @param string $path
	 * @param resource $stream
	 * @param int $size
	 * @return int
	 */
	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getDirectoryContent($directory): \Traversable
 {
 }
}
