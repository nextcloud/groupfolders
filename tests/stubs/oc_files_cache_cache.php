<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Cache;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OC\DB\Exceptions\DbalException;
use OC\DB\QueryBuilder\Sharded\ShardDefinition;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OC\Files\Storage\Wrapper\Encryption;
use OC\SystemConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\CacheEntryInsertedEvent;
use OCP\Files\Cache\CacheEntryRemovedEvent;
use OCP\Files\Cache\CacheEntryUpdatedEvent;
use OCP\Files\Cache\CacheInsertEvent;
use OCP\Files\Cache\CacheUpdateEvent;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchOperator;
use OCP\Files\Search\ISearchQuery;
use OCP\Files\Storage\IStorage;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\IDBConnection;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * Metadata cache for a storage
 *
 * The cache stores the metadata for all files and folders in a storage and is kept up to date through the following mechanisms:
 *
 * - Scanner: scans the storage and updates the cache where needed
 * - Watcher: checks for changes made to the filesystem outside of the Nextcloud instance and rescans files and folder when a change is detected
 * - Updater: listens to changes made to the filesystem inside of the Nextcloud instance and updates the cache where needed
 * - ChangePropagator: updates the mtime and etags of parent folders whenever a change to the cache is made to the cache by the updater
 */
class Cache implements ICache {
	use MoveFromCacheTrait {
		MoveFromCacheTrait::moveFromCache as moveFromCacheFallback;
	}

	/**
	 * @var array partial data for the cache
	 */
	protected array $partial = [];
	protected string $storageId;
	protected Storage $storageCache;
	protected IMimeTypeLoader$mimetypeLoader;
	protected IDBConnection $connection;
	protected SystemConfig $systemConfig;
	protected LoggerInterface $logger;
	protected QuerySearchHelper $querySearchHelper;
	protected IEventDispatcher $eventDispatcher;
	protected IFilesMetadataManager $metadataManager;

	public function __construct(
     private IStorage $storage,
     // this constructor is used in to many pleases to easily do proper di
     // so instead we group it all together
     ?CacheDependencies $dependencies = null
 )
 {
 }

	protected function getQueryBuilder()
 {
 }

	public function getStorageCache(): Storage
 {
 }

	/**
	 * Get the numeric storage id for this cache's storage
	 *
	 * @return int
	 */
	public function getNumericStorageId()
 {
 }

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param string | int $file either the path of a file or folder or the file id for a file or folder
	 * @return ICacheEntry|false the cache entry as array or false if the file is not found in the cache
	 */
	public function get($file)
 {
 }

	/**
	 * Create a CacheEntry from database row
	 *
	 * @param array $data
	 * @param IMimeTypeLoader $mimetypeLoader
	 * @return CacheEntry
	 */
	public static function cacheEntryFromData($data, IMimeTypeLoader $mimetypeLoader)
 {
 }

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param string $folder
	 * @return ICacheEntry[]
	 */
	public function getFolderContents($folder)
 {
 }

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param int $fileId the file id of the folder
	 * @return ICacheEntry[]
	 */
	public function getFolderContentsById($fileId)
 {
 }

	/**
	 * insert or update meta data for a file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 */
	public function put($file, array $data)
 {
 }

	/**
	 * insert meta data for a new file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 * @throws \RuntimeException
	 */
	public function insert($file, array $data)
 {
 }

	/**
	 * update the metadata of an existing file or folder in the cache
	 *
	 * @param int $id the fileid of the existing file or folder
	 * @param array $data [$key => $value] the metadata to update, only the fields provided in the array will be updated, non-provided values will remain unchanged
	 */
	public function update($id, array $data)
 {
 }

	/**
	 * extract query parts and params array from data array
	 *
	 * @param array $data
	 * @return array
	 */
	protected function normalizeData(array $data): array
 {
 }

	/**
	 * get the file id for a file
	 *
	 * A file id is a numeric id for a file or folder that's unique within an owncloud instance which stays the same for the lifetime of a file
	 *
	 * File ids are easiest way for apps to store references to a file since unlike paths they are not affected by renames or sharing
	 *
	 * @param string $file
	 * @return int
	 */
	public function getId($file)
 {
 }

	/**
	 * get the id of the parent folder of a file
	 *
	 * @param string $file
	 * @return int
	 */
	public function getParentId($file)
 {
 }

	/**
	 * check if a file is available in the cache
	 *
	 * @param string $file
	 * @return bool
	 */
	public function inCache($file)
 {
 }

	/**
	 * remove a file or folder from the cache
	 *
	 * when removing a folder from the cache all files and folders inside the folder will be removed as well
	 *
	 * @param string $file
	 */
	public function remove($file)
 {
 }

	/**
	 * Move a file or folder in the cache
	 *
	 * @param string $source
	 * @param string $target
	 */
	public function move($source, $target)
 {
 }

	/**
	 * Get the storage id and path needed for a move
	 *
	 * @param string $path
	 * @return array [$storageId, $internalPath]
	 */
	protected function getMoveInfo($path)
 {
 }

	protected function hasEncryptionWrapper(): bool
 {
 }

	/**
	 * Move a file or folder in the cache
	 *
	 * @param ICache $sourceCache
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @throws \OC\DatabaseException
	 * @throws \Exception if the given storages have an invalid id
	 */
	public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath)
 {
 }

	/**
	 * remove all entries for files that are stored on the storage from the cache
	 */
	public function clear()
 {
 }

	/**
	 * Get the scan status of a file
	 *
	 * - Cache::NOT_FOUND: File is not in the cache
	 * - Cache::PARTIAL: File is not stored in the cache but some incomplete data is known
	 * - Cache::SHALLOW: The folder and it's direct children are in the cache but not all sub folders are fully scanned
	 * - Cache::COMPLETE: The file or folder, with all it's children) are fully scanned
	 *
	 * @param string $file
	 *
	 * @return int Cache::NOT_FOUND, Cache::PARTIAL, Cache::SHALLOW or Cache::COMPLETE
	 */
	public function getStatus($file)
 {
 }

	/**
	 * search for files matching $pattern
	 *
	 * @param string $pattern the search pattern using SQL search syntax (e.g. '%searchstring%')
	 * @return ICacheEntry[] an array of cache entries where the name matches the search pattern
	 */
	public function search($pattern)
 {
 }

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype either a full mimetype to search ('text/plain') or only the first part of a mimetype ('image')
	 *                         where it will search for all mimetypes in the group ('image/*')
	 * @return ICacheEntry[] an array of cache entries where the mimetype matches the search
	 */
	public function searchByMime($mimetype)
 {
 }

	public function searchQuery(ISearchQuery $query)
 {
 }

	/**
	 * Re-calculate the folder size and the size of all parent folders
	 *
	 * @param string|boolean $path
	 * @param array $data (optional) meta data of the folder
	 */
	public function correctFolderSize($path, $data = null, $isBackgroundScan = false)
 {
 }

	/**
	 * get the incomplete count that shares parent $folder
	 *
	 * @param int $fileId the file id of the folder
	 * @return int
	 */
	public function getIncompleteChildrenCount($fileId)
 {
 }

	/**
	 * calculate the size of a folder and set it in the cache
	 *
	 * @param string $path
	 * @param array|null|ICacheEntry $entry (optional) meta data of the folder
	 * @return int|float
	 */
	public function calculateFolderSize($path, $entry = null)
 {
 }


	/**
	 * inner function because we can't add new params to the public function without breaking any child classes
	 *
	 * @param string $path
	 * @param array|null|ICacheEntry $entry (optional) meta data of the folder
	 * @param bool $ignoreUnknown don't mark the folder size as unknown if any of it's children are unknown
	 * @return int|float
	 */
	protected function calculateFolderSizeInner(string $path, $entry = null, bool $ignoreUnknown = false)
 {
 }

	/**
	 * get all file ids on the files on the storage
	 *
	 * @return int[]
	 */
	public function getAll()
 {
 }

	/**
	 * find a folder in the cache which has not been fully scanned
	 *
	 * If multiple incomplete folders are in the cache, the one with the highest id will be returned,
	 * use the one with the highest id gives the best result with the background scanner, since that is most
	 * likely the folder where we stopped scanning previously
	 *
	 * @return string|false the path of the folder or false when no folder matched
	 */
	public function getIncomplete()
 {
 }

	/**
	 * get the path of a file on this storage by it's file id
	 *
	 * @param int $id the file id of the file or folder to search
	 * @return string|null the path of the file (relative to the storage) or null if a file with the given id does not exists within this cache
	 */
	public function getPathById($id)
 {
 }

	/**
	 * get the storage id of the storage for a file and the internal path of the file
	 * unlike getPathById this does not limit the search to files on this storage and
	 * instead does a global search in the cache table
	 *
	 * @param int $id
	 * @return array first element holding the storage id, second the path
	 * @deprecated 17.0.0 use getPathById() instead
	 */
	public static function getById($id)
 {
 }

	/**
	 * normalize the given path
	 *
	 * @param string $path
	 * @return string
	 */
	public function normalize($path)
 {
 }

	/**
	 * Copy a file or folder in the cache
	 *
	 * @param ICache $sourceCache
	 * @param ICacheEntry $sourceEntry
	 * @param string $targetPath
	 * @return int fileId of copied entry
	 */
	public function copyFromCache(ICache $sourceCache, ICacheEntry $sourceEntry, string $targetPath): int
 {
 }

	public function getQueryFilterForStorage(): ISearchOperator
 {
 }

	public function getCacheEntryFromSearchResult(ICacheEntry $rawEntry): ?ICacheEntry
 {
 }
}
