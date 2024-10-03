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
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IScanner;
use OCP\Files\FileInfo;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\ObjectStore\IObjectStoreMultiPartUpload;
use OCP\Files\Storage\IChunkedFileWrite;
use OCP\Files\Storage\IStorage;
use Psr\Log\LoggerInterface;

class ObjectStoreStorage extends \OC\Files\Storage\Common implements IChunkedFileWrite {
	use CopyDirectory;

	protected IObjectStore $objectStore;
	protected string $id;
	protected bool $validateWrites = true;

	/**
	 * @param array $params
	 * @throws \Exception
	 */
	public function __construct($params)
 {
 }

	public function mkdir($path, bool $force = false): bool
 {
 }

	/**
	 * Object Stores use a NoopScanner because metadata is directly stored in
	 * the file cache and cannot really scan the filesystem. The storage passed in is not used anywhere.
	 */
	public function getScanner($path = '', $storage = null): IScanner
 {
 }

	public function getId(): string
 {
 }

	public function rmdir($path): bool
 {
 }

	public function unlink($path): bool
 {
 }

	public function rmObject(ICacheEntry $entry): bool
 {
 }

	public function stat($path): array|false
 {
 }

	public function getPermissions($path): int
 {
 }

	/**
	 * Override this method if you need a different unique resource identifier for your object storage implementation.
	 * The default implementations just appends the fileId to 'urn:oid:'. Make sure the URN is unique over all users.
	 * You may need a mapping table to store your URN if it cannot be generated from the fileid.
	 *
	 * @param int $fileId the fileid
	 * @return null|string the unified resource name used to identify the object
	 */
	public function getURN($fileId)
 {
 }

	public function opendir($path)
 {
 }

	public function filetype($path): string|false
 {
 }

	public function fopen($path, $mode)
 {
 }

	public function file_exists($path): bool
 {
 }

	public function rename($source, $target): bool
 {
 }

	public function getMimeType($path): string|false
 {
 }

	public function touch($path, $mtime = null): bool
 {
 }

	public function writeBack($tmpFile, $path)
 {
 }

	public function hasUpdated($path, $time): bool
 {
 }

	public function needsPartFile(): bool
 {
 }

	public function file_put_contents($path, $data): int
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getObjectStore(): IObjectStore
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false): bool
 {
 }

	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, ?ICacheEntry $sourceCacheEntry = null): bool
 {
 }

	public function copy($source, $target): bool
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
}
