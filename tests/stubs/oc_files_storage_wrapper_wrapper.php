<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Storage\FailedStorage;
use OC\Files\Storage\Storage;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IPropagator;
use OCP\Files\Cache\IScanner;
use OCP\Files\Cache\IUpdater;
use OCP\Files\Cache\IWatcher;
use OCP\Files\GenericFileException;
use OCP\Files\Storage\ILockingStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IWriteStreamStorage;
use OCP\Lock\ILockingProvider;
use OCP\Server;
use Override;
use Psr\Log\LoggerInterface;

class Wrapper implements Storage, ILockingStorage, IWriteStreamStorage {
	protected ?IStorage $storage = null;

	public ?ICache $cache = null;

	public ?IScanner $scanner = null;

	public ?IWatcher $watcher = null;

	public ?IPropagator $propagator = null;

	public ?IUpdater $updater = null;

	/**
	 * @param array{storage: IStorage, ...} $parameters
	 */
	public function __construct(array $parameters)
    {
    }

	public function getWrapperStorage(): Storage
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
    public function getScanner(string $path = '', ?IStorage $storage = null): IScanner
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
    public function getPropagator(?IStorage $storage = null): IPropagator
    {
    }

	#[\Override]
    public function getUpdater(?IStorage $storage = null): IUpdater
    {
    }

	#[\Override]
    public function getStorageCache(): \OC\Files\Cache\Storage
    {
    }

	#[\Override]
    public function getETag(string $path): string|false
    {
    }

	#[\Override]
    public function test(): bool
    {
    }

	#[\Override]
    public function isLocal(): bool
    {
    }

	#[\Override]
    public function instanceOfStorage(string $class): bool
    {
    }

	/**
	 * @psalm-template T of IStorage
	 * @psalm-param class-string<T> $class
	 * @psalm-return T|null
	 */
	public function getInstanceOfStorage(string $class): ?IStorage
    {
    }

	/**
	 * Pass any methods custom to specific storage implementations to the wrapped storage
	 *
	 * @return mixed
	 */
	public function __call(string $method, array $args)
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
    public function getAvailability(): array
    {
    }

	#[\Override]
    public function setAvailability(bool $isAvailable): void
    {
    }

	#[\Override]
    public function verifyPath(string $path, string $fileName): void
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

	public function isWrapperOf(IStorage $storage): bool
    {
    }

	#[\Override]
    public function setOwner(?string $user): void
    {
    }
}
