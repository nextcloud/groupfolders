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
use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IPropagator;
use OCP\Files\Cache\IScanner;
use OCP\Files\Cache\IUpdater;
use OCP\Files\Cache\IWatcher;
use OCP\Files\ForbiddenException;
use OCP\Files\GenericFileException;
use OCP\Files\IFilenameValidator;
use OCP\Files\InvalidPathException;
use OCP\Files\Storage\IConstructableStorage;
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
abstract class Common implements Storage, ILockingStorage, IWriteStreamStorage, IConstructableStorage {
	use LocalTempFileTrait;

	protected ?Cache $cache = null;
	protected ?Scanner $scanner = null;
	protected ?Watcher $watcher = null;
	protected ?Propagator $propagator = null;
	protected $storageCache;
	protected ?Updater $updater = null;

	protected array $mountOptions = [];
	protected $owner = null;

	public function __construct($parameters) {
	}

	/**
	 * Remove a file or folder
	 *
	 * @param string $path
	 */
	protected function remove($path): bool
 {
 }

	public function is_dir($path): bool
 {
 }

	public function is_file($path): bool
 {
 }

	public function filesize($path): int|float|false
 {
 }

	public function isReadable($path): bool
 {
 }

	public function isUpdatable($path): bool
 {
 }

	public function isCreatable($path): bool
 {
 }

	public function isDeletable($path): bool
 {
 }

	public function isSharable($path): bool
 {
 }

	public function getPermissions($path): int
 {
 }

	public function filemtime($path): int|false
 {
 }

	public function file_get_contents($path): string|false
 {
 }

	public function file_put_contents($path, $data): int|float|false
 {
 }

	public function rename($source, $target): bool
 {
 }

	public function copy($source, $target): bool
 {
 }

	public function getMimeType($path): string|false
 {
 }

	public function hash($type, $path, $raw = false): string|false
 {
 }

	public function getLocalFile($path): string|false
 {
 }

	protected function searchInDir($query, $dir = ''): array
 {
 }

	/**
	 * @inheritDoc
	 * Check if a file or folder has been updated since $time
	 *
	 * The method is only used to check if the cache needs to be updated. Storage backends that don't support checking
	 * the mtime should always return false here. As a result storage implementations that always return false expect
	 * exclusive access to the backend and will not pick up files that have been added in a way that circumvents
	 * Nextcloud filesystem.
	 */
	public function hasUpdated($path, $time): bool
 {
 }

	protected function getCacheDependencies(): CacheDependencies
 {
 }

	public function getCache($path = '', $storage = null): ICache
 {
 }

	public function getScanner($path = '', $storage = null): IScanner
 {
 }

	public function getWatcher($path = '', $storage = null): IWatcher
 {
 }

	public function getPropagator($storage = null): IPropagator
 {
 }

	public function getUpdater($storage = null): IUpdater
 {
 }

	public function getStorageCache($storage = null): \OC\Files\Cache\Storage
 {
 }

	public function getOwner($path): string|false
 {
 }

	public function getETag($path): string|false
 {
 }

	/**
	 * clean a path, i.e. remove all redundant '.' and '..'
	 * making sure that it can't point to higher than '/'
	 *
	 * @param string $path The path to clean
	 * @return string cleaned path
	 */
	public function cleanPath($path): string
 {
 }

	/**
	 * Test a storage for availability
	 */
	public function test(): bool
 {
 }

	public function free_space($path): int|float|false
 {
 }

	public function isLocal(): bool
 {
 }

	/**
	 * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
	 *
	 * @param string $class
	 */
	public function instanceOfStorage($class): bool
 {
 }

	/**
	 * A custom storage implementation can return an url for direct download of a give file.
	 *
	 * For now the returned array can hold the parameter url - in future more attributes might follow.
	 *
	 * @param string $path
	 */
	public function getDirectDownload($path): array|false
 {
 }

	public function verifyPath($path, $fileName): void
 {
 }

	/**
	 * Get the filename validator
	 * (cached for performance)
	 */
	protected function getFilenameValidator(): IFilenameValidator
 {
 }

	public function setMountOptions(array $options): void
 {
 }

	/**
	 * @param string $name
	 * @param mixed $default
	 */
	public function getMountOption($name, $default = null): mixed
 {
 }

	/**
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false): bool
 {
 }

	/**
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 */
	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath): bool
 {
 }

	public function getMetaData($path): ?array
 {
 }

	public function acquireLock($path, $type, ILockingProvider $provider): void
 {
 }

	public function releaseLock($path, $type, ILockingProvider $provider): void
 {
 }

	public function changeLock($path, $type, ILockingProvider $provider): void
 {
 }

	/**
	 * @return array [ available, last_checked ]
	 */
	public function getAvailability(): array
 {
 }

	public function setAvailability($isAvailable): void
 {
 }

	public function setOwner(?string $user): void
 {
 }

	public function needsPartFile(): bool
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getDirectoryContent($directory): \Traversable|false
 {
 }
}
