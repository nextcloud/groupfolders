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
use OC\Files\Storage\Wrapper\Encryption;
use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;
use OCP\Files;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IPropagator;
use OCP\Files\Cache\IScanner;
use OCP\Files\Cache\IUpdater;
use OCP\Files\Cache\IWatcher;
use OCP\Files\FileInfo;
use OCP\Files\ForbiddenException;
use OCP\Files\GenericFileException;
use OCP\Files\IFilenameValidator;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\InvalidPathException;
use OCP\Files\Storage\IConstructableStorage;
use OCP\Files\Storage\ILockingStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Server;
use OCP\Util;
use Override;
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

	public function __construct(array $parameters) {
	}

	protected function remove(string $path): bool
    {
    }

	#[\Override]
    public function is_dir(string $path): bool
    {
    }

	#[\Override]
    public function is_file(string $path): bool
    {
    }

	#[\Override]
    public function filesize(string $path): int|float|false
    {
    }

	#[\Override]
    public function isReadable(string $path): bool
    {
    }

	#[\Override]
    public function isUpdatable(string $path): bool
    {
    }

	#[\Override]
    public function isCreatable(string $path): bool
    {
    }

	#[\Override]
    public function isDeletable(string $path): bool
    {
    }

	#[\Override]
    public function isSharable(string $path): bool
    {
    }

	#[\Override]
    public function getPermissions(string $path): int
    {
    }

	#[\Override]
    public function filemtime(string $path): int|false
    {
    }

	#[\Override]
    public function file_get_contents(string $path): string|false
    {
    }

	#[\Override]
    public function file_put_contents(string $path, mixed $data): int|float|false
    {
    }

	#[\Override]
    public function rename(string $source, string $target): bool
    {
    }

	#[\Override]
    public function copy(string $source, string $target): bool
    {
    }

	#[\Override]
    public function getMimeType(string $path): string|false
    {
    }

	#[\Override]
    public function hash(string $type, string $path, bool $raw = false): string|false
    {
    }

	#[\Override]
    public function getLocalFile(string $path): string|false
    {
    }

	protected function searchInDir(string $query, string $dir = ''): array
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
	#[\Override]
    public function hasUpdated(string $path, int $time): bool
    {
    }

	protected function getCacheDependencies(): CacheDependencies
    {
    }

	#[\Override]
    public function getCache(string $path = '', ?IStorage $storage = null): ICache
    {
    }

	#[\Override]
    public function getScanner(string $path = '', ?IStorage $storage = null): IScanner
    {
    }

	#[\Override]
    public function getWatcher(string $path = '', ?IStorage $storage = null): IWatcher
    {
    }

	#[\Override]
    public function getPropagator(?IStorage $storage = null): IPropagator
    {
    }

	#[\Override]
    public function getUpdater(?IStorage $storage = null): IUpdater
    {
    }

	#[\Override]
    public function getStorageCache(?IStorage $storage = null): \OC\Files\Cache\Storage
    {
    }

	#[\Override]
    public function getOwner(string $path): string|false
    {
    }

	#[\Override]
    public function getETag(string $path): string|false
    {
    }

	/**
	 * clean a path, i.e. remove all redundant '.' and '..'
	 * making sure that it can't point to higher than '/'
	 *
	 * @param string $path The path to clean
	 * @return string cleaned path
	 */
	public function cleanPath(string $path): string
    {
    }

	/**
	 * Test a storage for availability
	 */
	#[\Override]
    public function test(): bool
    {
    }

	#[\Override]
    public function free_space(string $path): int|float|false
    {
    }

	#[\Override]
    public function isLocal(): bool
    {
    }

	/**
	 * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
	 */
	#[\Override]
    public function instanceOfStorage(string $class): bool
    {
    }

	#[Override]
    public function getDirectDownload(string $path): array|false
    {
    }

	#[Override]
    public function getDirectDownloadById(string $fileId): array|false
    {
    }

	#[\Override]
    public function verifyPath(string $path, string $fileName): void
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

	public function getMountOption(string $name, mixed $default = null): mixed
    {
    }

	#[\Override]
    public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath, bool $preserveMtime = false): bool
    {
    }

	#[\Override]
    public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool
    {
    }

	#[\Override]
    public function getMetaData(string $path): ?array
    {
    }

	#[\Override]
    public function acquireLock(string $path, int $type, ILockingProvider $provider): void
    {
    }

	#[\Override]
    public function releaseLock(string $path, int $type, ILockingProvider $provider): void
    {
    }

	#[\Override]
    public function changeLock(string $path, int $type, ILockingProvider $provider): void
    {
    }

	/**
	 * @return array{available: bool, last_checked: int}
	 */
	#[\Override]
    public function getAvailability(): array
    {
    }

	#[\Override]
    public function setAvailability(bool $isAvailable): void
    {
    }

	#[\Override]
    public function setOwner(?string $user): void
    {
    }

	#[\Override]
    public function needsPartFile(): bool
    {
    }

	#[\Override]
    public function writeStream(string $path, $stream, ?int $size = null): int
    {
    }

	#[\Override]
    public function getDirectoryContent(string $directory): \Traversable
    {
    }
}
