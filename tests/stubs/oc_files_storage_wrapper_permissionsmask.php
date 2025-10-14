<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Cache\Wrapper\CachePermissionsMask;
use OCP\Constants;
use OCP\Files\Storage\IStorage;

/**
 * Mask the permissions of a storage
 *
 * This can be used to restrict update, create, delete and/or share permissions of a storage
 *
 * Note that the read permissions can't be masked
 */
class PermissionsMask extends Wrapper {
	/**
	 * @param array $parameters ['storage' => $storage, 'mask' => $mask]
	 *
	 * $storage: The storage the permissions mask should be applied on
	 * $mask: The permission bits that should be kept, a combination of the \OCP\Constant::PERMISSION_ constants
	 */
	public function __construct(array $parameters)
 {
 }

	public function isUpdatable(string $path): bool
 {
 }

	public function isCreatable(string $path): bool
 {
 }

	public function isDeletable(string $path): bool
 {
 }

	public function isSharable(string $path): bool
 {
 }

	public function getPermissions(string $path): int
 {
 }

	public function rename(string $source, string $target): bool
 {
 }

	public function copy(string $source, string $target): bool
 {
 }

	public function touch(string $path, ?int $mtime = null): bool
 {
 }

	public function mkdir(string $path): bool
 {
 }

	public function rmdir(string $path): bool
 {
 }

	public function unlink(string $path): bool
 {
 }

	public function file_put_contents(string $path, mixed $data): int|float|false
 {
 }

	public function fopen(string $path, string $mode)
 {
 }

	public function getCache(string $path = '', ?IStorage $storage = null): \OCP\Files\Cache\ICache
 {
 }

	public function getMetaData(string $path): ?array
 {
 }

	public function getScanner(string $path = '', ?IStorage $storage = null): \OCP\Files\Cache\IScanner
 {
 }

	public function getDirectoryContent(string $directory): \Traversable
 {
 }
}
