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

	public function mkdir($path, bool $force = false)
 {
 }

	/**
	 * Object Stores use a NoopScanner because metadata is directly stored in
	 * the file cache and cannot really scan the filesystem. The storage passed in is not used anywhere.
	 */
	public function getScanner($path = '', $storage = null)
 {
 }

	public function getId()
 {
 }

	public function rmdir($path)
 {
 }

	public function unlink($path)
 {
 }

	public function rmObject(ICacheEntry $entry): bool
 {
 }

	public function stat($path)
 {
 }

	public function getPermissions($path)
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

	public function filetype($path)
 {
 }

	public function fopen($path, $mode)
 {
 }

	public function file_exists($path)
 {
 }

	public function rename($source, $target)
 {
 }

	public function getMimeType($path)
 {
 }

	public function touch($path, $mtime = null)
 {
 }

	public function writeBack($tmpFile, $path)
 {
 }

	/**
	 * external changes are not supported, exclusive access to the object storage is assumed
	 *
	 * @param string $path
	 * @param int $time
	 * @return false
	 */
	public function hasUpdated($path, $time)
 {
 }

	public function needsPartFile()
 {
 }

	public function file_put_contents($path, $data)
 {
 }

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }

	public function getObjectStore(): IObjectStore
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false)
 {
 }

	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, ?ICacheEntry $sourceCacheEntry = null): bool
 {
 }

	public function copy($source, $target)
 {
 }

	public function startChunkedWrite(string $targetPath): string
 {
 }

	/**
	 *
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
}
