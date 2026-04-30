<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage;

use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Encryption;
use OC\Files\Storage\Wrapper\Jail;
use OC\LargeFileHelper;
use OCA\FilesAccessControl\StorageWrapper;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\ForbiddenException;
use OCP\Files\GenericFileException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\Server;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * for local filestore, we only have to map the paths
 */
class Local extends Common {
	protected $datadir;

	protected $dataDirLength;

	protected $realDataDir;

	protected bool $unlinkOnTruncate;

	protected bool $caseInsensitive = false;

	public function __construct(array $parameters)
    {
    }

	public function __destruct() {
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
    public function getMetaData(string $path): ?array
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
    public function isReadable(string $path): bool
    {
    }

	#[\Override]
    public function isUpdatable(string $path): bool
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
    public function touch(string $path, ?int $mtime = null): bool
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
    public function hash(string $type, string $path, bool $raw = false): string|false
    {
    }

	#[\Override]
    public function free_space(string $path): int|float|false
    {
    }

	public function search(string $query): array
    {
    }

	#[\Override]
    public function getLocalFile(string $path): string|false
    {
    }

	#[\Override]
    protected function searchInDir(string $query, string $dir = ''): array
    {
    }

	#[\Override]
    public function hasUpdated(string $path, int $time): bool
    {
    }

	/**
	 * Get the source path (on disk) of a given path
	 *
	 * @throws ForbiddenException
	 */
	public function getSourcePath(string $path): string
    {
    }

	#[\Override]
    public function isLocal(): bool
    {
    }

	#[\Override]
    public function getETag(string $path): string|false
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
    public function writeStream(string $path, $stream, ?int $size = null): int
    {
    }
}
