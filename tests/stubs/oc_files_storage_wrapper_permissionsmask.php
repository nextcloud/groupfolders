<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Cache\Wrapper\CachePermissionsMask;
use OC\Files\Storage\Storage;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\IScanner;
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
	 * @var int the permissions bits we want to keep
	 */
	protected readonly int $mask;

	/**
	 * @param array{storage: IStorage, mask: int, ...} $parameters
	 *
	 * $storage: The storage the permissions mask should be applied on
	 * $mask: The permission bits that should be kept, a combination of the \OCP\Constant::PERMISSION_ constants
	 */
	public function __construct(array $parameters)
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
    public function rename(string $source, string $target): bool
    {
    }

	#[\Override]
    public function copy(string $source, string $target): bool
    {
    }

	#[\Override]
    public function touch(string $path, ?int $mtime = null): bool
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
    public function unlink(string $path): bool
    {
    }

	#[\Override]
    public function file_put_contents(string $path, mixed $data): int|float|false
    {
    }

	#[\Override]
    public function fopen(string $path, string $mode)
    {
    }

	#[\Override]
    public function getCache(string $path = '', ?IStorage $storage = null): ICache
    {
    }

	#[\Override]
    public function getMetaData(string $path): ?array
    {
    }

	#[\Override]
    public function getScanner(string $path = '', ?IStorage $storage = null): IScanner
    {
    }

	#[\Override]
    public function getDirectoryContent(string $directory): \Traversable
    {
    }
}
