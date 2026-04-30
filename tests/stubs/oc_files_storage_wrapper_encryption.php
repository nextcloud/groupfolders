<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Encryption\Exceptions\ModuleDoesNotExistsException;
use OC\Encryption\Util;
use OC\Files\Cache\CacheEntry;
use OC\Files\Filesystem;
use OC\Files\Mount\Manager;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Common;
use OC\Files\Storage\LocalTempFileTrait;
use OC\Memcache\ArrayCache;
use OCP\Cache\CappedMemoryCache;
use OCP\Encryption\Exceptions\InvalidHeaderException;
use OCP\Encryption\IEncryptionModule;
use OCP\Encryption\IFile;
use OCP\Encryption\IManager;
use OCP\Encryption\Keys\IStorage;
use OCP\Files;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\GenericFileException;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage;
use Psr\Log\LoggerInterface;

class Encryption extends Wrapper {
	use LocalTempFileTrait;
	protected array $unencryptedSize = [];

	/**
	 * @param array{storage: Storage\IStorage, ...} $parameters
	 */
	public function __construct(array $parameters, private IManager $encryptionManager, private Util $util, private LoggerInterface $logger, private IFile $fileHelper, private ?string $uid, private IStorage $keyStorage, private Manager $mountManager, private ArrayCache $arrayCache)
    {
    }

	#[\Override]
    public function filesize(string $path): int|float|false
    {
    }

	#[\Override]
    public function getMetaData(string $path): ?array
    {
    }

	#[\Override]
    public function getDirectoryContent(string $directory): \Traversable
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
    public function rmdir(string $path): bool
    {
    }

	#[\Override]
    public function isReadable(string $path): bool
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


	/**
	 * perform some plausibility checks if the unencrypted size is correct.
	 * If not, we calculate the correct unencrypted size and return it
	 *
	 * @param string $path internal path relative to the storage root
	 * @param int $unencryptedSize size of the unencrypted file
	 *
	 * @return int unencrypted size
	 */
	protected function verifyUnencryptedSize(string $path, int $unencryptedSize): int
    {
    }

	/**
	 * calculate the unencrypted size
	 *
	 * @param string $path internal path relative to the storage root
	 * @param int $size size of the physical file
	 * @param int $unencryptedSize size of the unencrypted file
	 */
	protected function fixUnencryptedSize(string $path, int $size, int $unencryptedSize): int|float
    {
    }

	#[\Override]
    public function moveFromStorage(Storage\IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath, $preserveMtime = true): bool
    {
    }

	#[\Override]
    public function copyFromStorage(Storage\IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath, $preserveMtime = false, $isRename = false): bool
    {
    }

	#[\Override]
    public function getLocalFile(string $path): string|false
    {
    }

	#[\Override]
    public function isLocal(): bool
    {
    }

	#[\Override]
    public function stat(string $path): array|false
    {
    }

	#[\Override]
    public function hash(string $type, string $path, bool $raw = false): string|false
    {
    }

	/**
	 * return full path, including mount point
	 *
	 * @param string $path relative to mount point
	 * @return string full path including mount point
	 */
	protected function getFullPath(string $path): string
    {
    }

	/**
	 * read first block of encrypted file, typically this will contain the
	 * encryption header
	 */
	protected function readFirstBlock(string $path): string
    {
    }

	/**
	 * return header size of given file
	 */
	protected function getHeaderSize(string $path): int
    {
    }

	/**
	 * read header from file
	 */
	protected function getHeader(string $path): array
    {
    }

	/**
	 * read encryption module needed to read/write the file located at $path
	 *
	 * @throws ModuleDoesNotExistsException
	 * @throws \Exception
	 */
	protected function getEncryptionModule(string $path): ?IEncryptionModule
    {
    }

	public function updateUnencryptedSize(string $path, int|float $unencryptedSize): void
    {
    }

	/**
	 * copy keys to new location
	 *
	 * @param string $source path relative to data/
	 * @param string $target path relative to data/
	 */
	protected function copyKeys(string $source, string $target): bool
    {
    }

	/**
	 * check if path points to a files version
	 */
	protected function isVersion(string $path): bool
    {
    }

	/**
	 * check if the given storage should be encrypted or not
	 */
	public function shouldEncrypt(string $path): bool
    {
    }

	#[\Override]
    public function writeStream(string $path, $stream, ?int $size = null): int
    {
    }

	public function clearIsEncryptedCache(): void
    {
    }

	/**
	 * Allow temporarily disabling the wrapper
	 */
	public function setEnabled(bool $enabled): void
    {
    }

	/**
	 * Check if the on-disk data for a file has a valid encrypted header
	 *
	 * @param string $path
	 * @return bool
	 */
	public function hasValidHeader(string $path): bool
    {
    }
}
