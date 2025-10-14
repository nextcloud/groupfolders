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
use OCP\Constants;
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
class Local extends \OC\Files\Storage\Common {
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

	public function getId(): string
 {
 }

	public function mkdir(string $path): bool
 {
 }

	public function rmdir(string $path): bool
 {
 }

	public function opendir(string $path)
 {
 }

	public function is_dir(string $path): bool
 {
 }

	public function is_file(string $path): bool
 {
 }

	public function stat(string $path): array|false
 {
 }

	public function getMetaData(string $path): ?array
 {
 }

	public function filetype(string $path): string|false
 {
 }

	public function filesize(string $path): int|float|false
 {
 }

	public function isReadable(string $path): bool
 {
 }

	public function isUpdatable(string $path): bool
 {
 }

	public function file_exists(string $path): bool
 {
 }

	public function filemtime(string $path): int|false
 {
 }

	public function touch(string $path, ?int $mtime = null): bool
 {
 }

	public function file_get_contents(string $path): string|false
 {
 }

	public function file_put_contents(string $path, mixed $data): int|float|false
 {
 }

	public function unlink(string $path): bool
 {
 }

	public function rename(string $source, string $target): bool
 {
 }

	public function copy(string $source, string $target): bool
 {
 }

	public function fopen(string $path, string $mode)
 {
 }

	public function hash(string $type, string $path, bool $raw = false): string|false
 {
 }

	public function free_space(string $path): int|float|false
 {
 }

	public function search(string $query): array
 {
 }

	public function getLocalFile(string $path): string|false
 {
 }

	protected function searchInDir(string $query, string $dir = ''): array
 {
 }

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

	public function isLocal(): bool
 {
 }

	public function getETag(string $path): string|false
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath, bool $preserveMtime = false): bool
 {
 }

	public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }
}
