<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Cache\Wrapper;

use OC\Files\Cache\Cache;
use OC\Files\Cache\CacheDependencies;
use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchOperator;

/**
 * Jail to a subdirectory of the wrapped cache
 */
class CacheJail extends CacheWrapper {
	/**
	 * @var string
	 */
	protected $root;
	protected $unjailedRoot;

	public function __construct(?ICache $cache, string $root, ?CacheDependencies $dependencies = null)
 {
 }

	protected function getRoot()
 {
 }

	/**
	 * Get the root path with any nested jails resolved
	 *
	 * @return string
	 */
	protected function getGetUnjailedRoot()
 {
 }

	protected function getSourcePath($path)
 {
 }

	/**
	 * @param string $path
	 * @param null|string $root
	 * @return null|string the jailed path or null if the path is outside the jail
	 */
	protected function getJailedPath(string $path, ?string $root = null)
 {
 }

	protected function formatCacheEntry($entry)
 {
 }

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param string /int $file
	 * @return ICacheEntry|false
	 */
	public function get($file)
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
	 * update the metadata in the cache
	 *
	 * @param int $id
	 * @param array $data
	 */
	public function update($id, array $data)
 {
 }

	/**
	 * get the file id for a file
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

	/**
	 * remove all entries for files that are stored on the storage from the cache
	 */
	public function clear()
 {
 }

	/**
	 * @param string $file
	 *
	 * @return int Cache::NOT_FOUND, Cache::PARTIAL, Cache::SHALLOW or Cache::COMPLETE
	 */
	public function getStatus($file)
 {
 }

	/**
	 * update the folder size and the size of all parent folders
	 *
	 * @param string|boolean $path
	 * @param array $data (optional) meta data of the folder
	 */
	public function correctFolderSize($path, $data = null, $isBackgroundScan = false)
 {
 }

	/**
	 * get the size of a folder and set it in the cache
	 *
	 * @param string $path
	 * @param array|null|ICacheEntry $entry (optional) meta data of the folder
	 * @return int|float
	 */
	public function calculateFolderSize($path, $entry = null)
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
	 * If multiply incomplete folders are in the cache, the one with the highest id will be returned,
	 * use the one with the highest id gives the best result with the background scanner, since that is most
	 * likely the folder where we stopped scanning previously
	 *
	 * @return string|false the path of the folder or false when no folder matched
	 */
	public function getIncomplete()
 {
 }

	/**
	 * get the path of a file on this storage by it's id
	 *
	 * @param int $id
	 * @return string|null
	 */
	public function getPathById($id)
 {
 }

	/**
	 * Move a file or folder in the cache
	 *
	 * Note that this should make sure the entries are removed from the source cache
	 *
	 * @param \OCP\Files\Cache\ICache $sourceCache
	 * @param string $sourcePath
	 * @param string $targetPath
	 */
	public function moveFromCache(\OCP\Files\Cache\ICache $sourceCache, $sourcePath, $targetPath)
 {
 }

	public function getQueryFilterForStorage(): ISearchOperator
 {
 }

	protected function addJailFilterQuery(ISearchOperator $filter): ISearchOperator
 {
 }

	public function getCacheEntryFromSearchResult(ICacheEntry $rawEntry): ?ICacheEntry
 {
 }
}
