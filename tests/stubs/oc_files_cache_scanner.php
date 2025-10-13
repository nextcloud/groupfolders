<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Cache;

use Doctrine\DBAL\Exception;
use OC\Files\Storage\Wrapper\Encryption;
use OC\Files\Storage\Wrapper\Jail;
use OC\Hooks\BasicEmitter;
use OCP\Files\Cache\IScanner;
use OCP\Files\ForbiddenException;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\ILockingStorage;
use OCP\Files\Storage\IReliableEtagStorage;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Class Scanner
 *
 * Hooks available in scope \OC\Files\Cache\Scanner:
 *  - scanFile(string $path, string $storageId)
 *  - scanFolder(string $path, string $storageId)
 *  - postScanFile(string $path, string $storageId)
 *  - postScanFolder(string $path, string $storageId)
 *
 * @package OC\Files\Cache
 */
class Scanner extends BasicEmitter implements IScanner {
	/**
	 * @var \OC\Files\Storage\Storage $storage
	 */
	protected $storage;

	/**
	 * @var string $storageId
	 */
	protected $storageId;

	/**
	 * @var \OC\Files\Cache\Cache $cache
	 */
	protected $cache;

	/**
	 * @var boolean $cacheActive If true, perform cache operations, if false, do not affect cache
	 */
	protected $cacheActive;

	/**
	 * @var bool $useTransactions whether to use transactions
	 */
	protected $useTransactions = true;

	/**
	 * @var \OCP\Lock\ILockingProvider
	 */
	protected $lockingProvider;

	protected IDBConnection $connection;

	public function __construct(\OC\Files\Storage\Storage $storage)
 {
 }

	/**
	 * Whether to wrap the scanning of a folder in a database transaction
	 * On default transactions are used
	 *
	 * @param bool $useTransactions
	 */
	public function setUseTransactions($useTransactions): void
 {
 }

	/**
	 * get all the metadata of a file or folder
	 * *
	 *
	 * @param string $path
	 * @return array|null an array of metadata of the file
	 */
	protected function getData($path)
 {
 }

	/**
	 * scan a single file and store it in the cache
	 *
	 * @param string $file
	 * @param int $reuseExisting
	 * @param int $parentId
	 * @param array|CacheEntry|null|false $cacheData existing data in the cache for the file to be scanned
	 * @param bool $lock set to false to disable getting an additional read lock during scanning
	 * @param array|null $data the metadata for the file, as returned by the storage
	 * @return array|null an array of metadata of the scanned file
	 * @throws \OCP\Lock\LockedException
	 */
	public function scanFile($file, $reuseExisting = 0, $parentId = -1, $cacheData = null, $lock = true, $data = null)
 {
 }

	protected function removeFromCache($path)
 {
 }

	/**
	 * @param string $path
	 * @param array $data
	 * @param int $fileId
	 * @return int the id of the added file
	 */
	protected function addToCache($path, $data, $fileId = -1)
 {
 }

	/**
	 * @param string $path
	 * @param array $data
	 * @param int $fileId
	 */
	protected function updateCache($path, $data, $fileId = -1)
 {
 }

	/**
	 * scan a folder and all it's children
	 *
	 * @param string $path
	 * @param bool $recursive
	 * @param int $reuse
	 * @param bool $lock set to false to disable getting an additional read lock during scanning
	 * @return array|null an array of the meta data of the scanned file or folder
	 */
	public function scan($path, $recursive = self::SCAN_RECURSIVE, $reuse = -1, $lock = true)
 {
 }

	/**
	 * Compares $array1 against $array2 and returns all the values in $array1 that are not in $array2
	 * Note this is a one-way check - i.e. we don't care about things that are in $array2 that aren't in $array1
	 *
	 * Supports multi-dimensional arrays
	 * Also checks keys/indexes
	 * Comparisons are strict just like array_diff_assoc
	 * Order of keys/values does not matter
	 *
	 * @param array $array1
	 * @param array $array2
	 * @return array with the differences between $array1 and $array1
	 * @throws \InvalidArgumentException if $array1 isn't an actual array
	 *
	 */
	protected function array_diff_assoc_multi(array $array1, array $array2)
 {
 }

	/**
	 * Get the children currently in the cache
	 *
	 * @param int $folderId
	 * @return array<string, \OCP\Files\Cache\ICacheEntry>
	 */
	protected function getExistingChildren($folderId): array
 {
 }

	/**
	 * scan all the files and folders in a folder
	 *
	 * @param string $path
	 * @param bool|IScanner::SCAN_RECURSIVE_INCOMPLETE $recursive
	 * @param int $reuse a combination of self::REUSE_*
	 * @param int $folderId id for the folder to be scanned
	 * @param bool $lock set to false to disable getting an additional read lock during scanning
	 * @param int|float $oldSize the size of the folder before (re)scanning the children
	 * @return int|float the size of the scanned folder or -1 if the size is unknown at this stage
	 */
	protected function scanChildren(string $path, $recursive, int $reuse, int $folderId, bool $lock, int|float $oldSize, &$etagChanged = false)
 {
 }

	/**
	 * check if the file should be ignored when scanning
	 * NOTE: files with a '.part' extension are ignored as well!
	 *       prevents unfinished put requests to be scanned
	 *
	 * @param string $file
	 * @return boolean
	 */
	public static function isPartialFile($file)
 {
 }

	/**
	 * walk over any folders that are not fully scanned yet and scan them
	 */
	public function backgroundScan()
 {
 }

	protected function runBackgroundScanJob(callable $callback, $path)
 {
 }

	/**
	 * Set whether the cache is affected by scan operations
	 *
	 * @param boolean $active The active state of the cache
	 */
	public function setCacheActive($active)
 {
 }
}
