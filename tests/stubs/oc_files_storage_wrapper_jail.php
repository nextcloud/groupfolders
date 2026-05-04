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
use OC\Files\Storage\FailedStorage;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IPropagator;
use OCP\Files\Cache\IWatcher;
use OCP\Files\GenericFileException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use OCP\Server;
use Psr\Log\LoggerInterface;

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
	 * @param array $parameters ['storage' => $storage, 'root' => $root]
	 *
	 * $storage: The storage that will be wrapper
	 * $root: The folder in the wrapped storage that will become the root folder of the wrapped storage
	 */
	public function __construct(array $parameters)
    {
    }

	public function getUnjailedPath(string $path): string
    {
    }

	/**
	 * This is separate from Wrapper::getWrapperStorage so we can get the jailed storage consistently even if the jail is inside another wrapper
	 */
	public function getUnjailedStorage(): IStorage
    {
    }


	public function getJailedPath(string $path): ?string
    {
    }

	#[\Override]
    public function getId(): string
    {
    }

	#[\Override]
    public function mkdir(string $path): bool
    {
    }

	#[\Override]
    public function rmdir(string $path): bool
    {
    }

	#[\Override]
    public function opendir(string $path)
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
    public function stat(string $path): array|false
    {
    }

	#[\Override]
    public function filetype(string $path): string|false
    {
    }

	#[\Override]
    public function filesize(string $path): int|float|false
    {
    }

	#[\Override]
    public function isCreatable(string $path): bool
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
    public function file_exists(string $path): bool
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
    public function unlink(string $path): bool
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
    public function fopen(string $path, string $mode)
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
    public function free_space(string $path): int|float|false
    {
    }

	#[\Override]
    public function touch(string $path, ?int $mtime = null): bool
    {
    }

	#[\Override]
    public function getLocalFile(string $path): string|false
    {
    }

	#[\Override]
    public function hasUpdated(string $path, int $time): bool
    {
    }

	#[\Override]
    public function getCache(string $path = '', ?IStorage $storage = null): ICache
    {
    }

	#[\Override]
    public function getOwner(string $path): string|false
    {
    }

	#[\Override]
    public function getWatcher(string $path = '', ?IStorage $storage = null): IWatcher
    {
    }

	#[\Override]
    public function getETag(string $path): string|false
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
	 * Resolve the path for the source of the share.
	 *
	 * @return array{0: IStorage, 1: string}
	 */
	public function resolvePath(string $path): array
    {
    }

	#[\Override]
    public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool
    {
    }

	#[\Override]
    public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool
    {
    }

	#[\Override]
    public function getPropagator(?IStorage $storage = null): IPropagator
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
