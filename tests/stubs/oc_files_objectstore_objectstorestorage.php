<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\ObjectStore;

use Aws\S3\Exception\S3Exception;
use Aws\S3\Exception\S3MultipartUploadException;
use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Files\Cache\Cache;
use OC\Files\Cache\CacheEntry;
use OC\Files\Storage\PolyFill\CopyDirectory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IScanner;
use OCP\Files\FileInfo;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\ObjectStore\IObjectStoreMetaData;
use OCP\Files\ObjectStore\IObjectStoreMultiPartUpload;
use OCP\Files\Storage\IChunkedFileWrite;
use OCP\Files\Storage\IStorage;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Log\LoggerInterface;

class ObjectStoreStorage extends \OC\Files\Storage\Common implements IChunkedFileWrite {
	use CopyDirectory;

	protected IObjectStore $objectStore;
	protected string $id;
	protected bool $validateWrites = true;

	/**
	 * @param array $parameters
	 * @throws \Exception
	 */
	public function __construct(array $parameters)
 {
 }

	public function mkdir(string $path, bool $force = false, array $metadata = []): bool
 {
 }

	/**
	 * Object Stores use a NoopScanner because metadata is directly stored in
	 * the file cache and cannot really scan the filesystem. The storage passed in is not used anywhere.
	 */
	public function getScanner(string $path = '', ?IStorage $storage = null): IScanner
 {
 }

	public function getId(): string
 {
 }

	public function rmdir(string $path): bool
 {
 }

	public function unlink(string $path): bool
 {
 }

	public function rmObject(ICacheEntry $entry): bool
 {
 }

	public function stat(string $path): array|false
 {
 }

	public function getPermissions(string $path): int
 {
 }

	/**
	 * Override this method if you need a different unique resource identifier for your object storage implementation.
	 * The default implementations just appends the fileId to 'urn:oid:'. Make sure the URN is unique over all users.
	 * You may need a mapping table to store your URN if it cannot be generated from the fileid.
	 *
	 * @return string the unified resource name used to identify the object
	 */
	public function getURN(int $fileId): string
 {
 }

	public function opendir(string $path)
 {
 }

	public function filetype(string $path): string|false
 {
 }

	public function fopen(string $path, string $mode)
 {
 }

	public function file_exists(string $path): bool
 {
 }

	public function rename(string $source, string $target): bool
 {
 }

	public function getMimeType(string $path): string|false
 {
 }

	public function touch(string $path, ?int $mtime = null): bool
 {
 }

	public function writeBack(string $tmpFile, string $path)
 {
 }

	public function hasUpdated(string $path, int $time): bool
 {
 }

	public function needsPartFile(): bool
 {
 }

	public function file_put_contents(string $path, mixed $data): int
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getObjectStore(): IObjectStore
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath, bool $preserveMtime = false): bool
 {
 }

	public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath, ?ICacheEntry $sourceCacheEntry = null): bool
 {
 }

	public function copy(string $source, string $target): bool
 {
 }

	public function startChunkedWrite(string $targetPath): string
 {
 }

	/**
	 * @throws GenericFileException
	 */
	public function putChunkedWritePart(string $targetPath, string $writeToken, string $chunkId, $data, $size = null): ?array
 {
 }

	public function completeChunkedWrite(string $targetPath, string $writeToken): int
 {
 }

	public function cancelChunkedWrite(string $targetPath, string $writeToken): void
 {
 }

	public function setPreserveCacheOnDelete(bool $preserve)
 {
 }

	public function free_space(string $path): int|float|false
 {
 }
	
	public function getDirectDownloadById(string $fileId): array|false
 {
 }

	public function getDirectDownload(string $path): array|false
 {
 }

}
