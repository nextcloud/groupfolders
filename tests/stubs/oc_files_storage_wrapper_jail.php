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
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IPropagator;
use OCP\Files\Cache\IWatcher;
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

	public function getUnjailedPath($path): string
 {
 }

	/**
	 * This is separate from Wrapper::getWrapperStorage so we can get the jailed storage consistently even if the jail is inside another wrapper
	 */
	public function getUnjailedStorage(): IStorage
 {
 }


	public function getJailedPath($path): ?string
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

	public function getOwner($path): string|false
 {
 }

	public function getWatcher($path = '', $storage = null): IWatcher
 {
 }

	public function getETag($path): string|false
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
	 * Resolve the path for the source of the share
	 *
	 * @param string $path
	 */
	public function resolvePath($path): array
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath): bool
 {
 }

	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath): bool
 {
 }

	public function getPropagator($storage = null): IPropagator
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getDirectoryContent($directory): \Traversable
 {
 }
}
