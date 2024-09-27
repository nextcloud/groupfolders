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

	public function __construct($arguments)
 {
 }

	public function __destruct() {
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

	public function getMetaData($path): ?array
 {
 }

	public function filetype($path): string|false
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

	public function file_exists($path): bool
 {
 }

	public function filemtime($path): int|false
 {
 }

	public function touch($path, $mtime = null): bool
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

	public function hash($type, $path, $raw = false): string|false
 {
 }

	public function free_space($path): int|float|false
 {
 }

	public function search($query): array
 {
 }

	public function getLocalFile($path): string|false
 {
 }

	/**
	 * @param string $query
	 * @param string $dir
	 */
	protected function searchInDir($query, $dir = ''): array
 {
 }

	public function hasUpdated($path, $time): bool
 {
 }

	/**
	 * Get the source path (on disk) of a given path
	 *
	 * @param string $path
	 * @throws ForbiddenException
	 */
	public function getSourcePath($path): string
 {
 }

	public function isLocal(): bool
 {
 }

	public function getETag($path): string|false
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false): bool
 {
 }

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath): bool
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }
}
