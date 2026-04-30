<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Cache\Wrapper;

use OC\Files\Cache\Cache;
use OC\Files\Cache\CacheDependencies;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchOperator;
use OCP\Files\Search\ISearchQuery;
use OCP\Server;

class CacheWrapper extends Cache {
	public function __construct(protected ?ICache $cache, ?CacheDependencies $dependencies = null)
    {
    }

	public function getCache(): ICache
    {
    }

	#[\Override]
    protected function hasEncryptionWrapper(): bool
    {
    }

	#[\Override]
    protected function shouldEncrypt(string $targetPath): bool
    {
    }

	/**
	 * Make it easy for wrappers to modify every returned cache entry
	 *
	 * @param ICacheEntry $entry
	 * @return ICacheEntry|false
	 */
	protected function formatCacheEntry($entry)
    {
    }

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param string|int $file
	 * @return ICacheEntry|false
	 */
	#[\Override]
    public function get($file)
    {
    }

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param string $folder
	 * @return ICacheEntry[]
	 */
	#[\Override]
    public function getFolderContents(string $folder, ?string $mimeTypeFilter = null): array
    {
    }

	/**
	 * Get the metadata of all files stored in given folder
	 *
	 * @param int $fileId the file id of the folder
	 * @return ICacheEntry[]
	 */
	#[\Override]
    public function getFolderContentsById(int $fileId, ?string $mimeTypeFilter = null)
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
	#[\Override]
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
	#[\Override]
    public function insert($file, array $data)
    {
    }

	/**
	 * update the metadata in the cache
	 *
	 * @param int $id
	 * @param array $data
	 */
	#[\Override]
    public function update($id, array $data)
    {
    }

	/**
	 * get the file id for a file
	 *
	 * @param string $file
	 * @return int
	 */
	#[\Override]
    public function getId($file)
    {
    }

	/**
	 * get the id of the parent folder of a file
	 *
	 * @param string $file
	 * @return int
	 */
	#[\Override]
    public function getParentId($file)
    {
    }

	/**
	 * check if a file is available in the cache
	 *
	 * @param string $file
	 * @return bool
	 */
	#[\Override]
    public function inCache($file)
    {
    }

	/**
	 * remove a file or folder from the cache
	 *
	 * @param string $file
	 */
	#[\Override]
    public function remove($file)
    {
    }

	/**
	 * Move a file or folder in the cache
	 *
	 * @param string $source
	 * @param string $target
	 */
	#[\Override]
    public function move($source, $target)
    {
    }

	#[\Override]
    protected function getMoveInfo($path)
    {
    }

	#[\Override]
    public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath)
    {
    }

	/**
	 * remove all entries for files that are stored on the storage from the cache
	 */
	#[\Override]
    public function clear()
    {
    }

	/**
	 * @param string $file
	 *
	 * @return int Cache::NOT_FOUND, Cache::PARTIAL, Cache::SHALLOW or Cache::COMPLETE
	 */
	#[\Override]
    public function getStatus($file)
    {
    }

	#[\Override]
    public function searchQuery(ISearchQuery $query)
    {
    }

	/**
	 * update the folder size and the size of all parent folders
	 *
	 * @param array|ICacheEntry|null $data (optional) meta data of the folder
	 */
	#[\Override]
    public function correctFolderSize(string $path, $data = null, bool $isBackgroundScan = false): void
    {
    }

	/**
	 * get the size of a folder and set it in the cache
	 *
	 * @param string $path
	 * @param array|null|ICacheEntry $entry (optional) meta data of the folder
	 * @return int|float
	 */
	#[\Override]
    public function calculateFolderSize($path, $entry = null)
    {
    }

	/**
	 * get all file ids on the files on the storage
	 *
	 * @return int[]
	 */
	#[\Override]
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
	#[\Override]
    public function getIncomplete()
    {
    }

	/**
	 * get the path of a file on this storage by it's id
	 *
	 * @param int $id
	 * @return string|null
	 */
	#[\Override]
    public function getPathById($id)
    {
    }

	/**
	 * Returns the numeric storage id
	 *
	 * @return int
	 */
	#[\Override]
    public function getNumericStorageId()
    {
    }

	/**
	 * get the storage id of the storage for a file and the internal path of the file
	 * unlike getPathById this does not limit the search to files on this storage and
	 * instead does a global search in the cache table
	 *
	 * @param int $id
	 * @return array first element holding the storage id, second the path
	 */
	#[\Override]
    public static function getById($id)
    {
    }

	#[\Override]
    public function getQueryFilterForStorage(): ISearchOperator
    {
    }

	#[\Override]
    public function getCacheEntryFromSearchResult(ICacheEntry $rawEntry): ?ICacheEntry
    {
    }
}
