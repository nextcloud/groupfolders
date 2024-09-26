<?php

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

	public function getWrapperStorage(): Storage
 {
 }

	public function getId(): string
 {
 }

	public function mkdir($path): bool
 {
 }

	public function rmdir($path): bool
 {
 }

	public function opendir($path)
 {
 }

	public function is_dir($path): bool
 {
 }

	public function is_file($path): bool
 {
 }

	public function stat($path): array|false
 {
 }

	public function filetype($path): string|false
 {
 }

	public function filesize($path): int|float|false
 {
 }

	public function isCreatable($path): bool
 {
 }

	public function isReadable($path): bool
 {
 }

	public function isUpdatable($path): bool
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

	public function file_exists($path): bool
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

	public function unlink($path): bool
 {
 }

	public function rename($source, $target): bool
 {
 }

	public function copy($source, $target): bool
 {
 }

	public function fopen($path, $mode)
 {
 }

	public function getMimeType($path): string|false
 {
 }

	public function hash($type, $path, $raw = false): string|false
 {
 }

	public function free_space($path): int|float|false
 {
 }

	public function touch($path, $mtime = null): bool
 {
 }

	public function getLocalFile($path): string|false
 {
 }

	public function hasUpdated($path, $time): bool
 {
 }

	public function getCache($path = '', $storage = null): ICache
 {
 }

	public function getScanner($path = '', $storage = null): IScanner
 {
 }

	public function getOwner($path): string|false
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

	public function getStorageCache(): \OC\Files\Cache\Storage
 {
 }

	public function getETag($path): string|false
 {
 }

	public function test(): bool
 {
 }

	public function isLocal(): bool
 {
 }

	public function instanceOfStorage($class): bool
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
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call($method, $args)
 {
 }

	public function getDirectDownload($path): array|false
 {
 }

	public function getAvailability(): array
 {
 }

	public function setAvailability($isAvailable): void
 {
 }

	public function verifyPath($path, $fileName): void
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath): bool
 {
 }

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

	public function needsPartFile(): bool
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getDirectoryContent($directory): \Traversable|false
 {
 }

	public function isWrapperOf(IStorage $storage): bool
 {
 }

	public function setOwner(?string $user): void
 {
 }
}
