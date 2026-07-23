<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OC\Files\Cache\Wrapper\CacheWrapper;
use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchOperator;
use OCP\Files\Search\ISearchQuery;

class ACLCacheWrapper extends CacheWrapper {
	public function __construct(
		ICache $cache,
		private readonly ACLManager $aclManager,
		private readonly int $folderId,
		private readonly bool $inShare,
	) {
		parent::__construct($cache);
	}

	/**
	 * @param array<string, Rule[]> $rules
	 */
	private function getACLPermissionsForPath(string $path, array $rules = []): int {
		if ($rules) {
			$permissions = $this->aclManager->getPermissionsForPathFromRules($this->folderId, $path, $rules);
		} else {
			$permissions = $this->aclManager->getACLPermissionsForPath($this->folderId, $this->getNumericStorageId(), $path);
		}

		// if there is no read permissions, than deny everything
		if ($this->inShare) {
			$minPermissions = Constants::PERMISSION_READ + Constants::PERMISSION_SHARE;
		} else {
			$minPermissions = Constants::PERMISSION_READ;
		}

		$canRead = ($permissions & $minPermissions) === $minPermissions;

		return $canRead ? $permissions : 0;
	}

	/**
	 * @param array<string, Rule[]> $rules
	 */
	#[\Override]
	protected function formatCacheEntry($entry, array $rules = []): ICacheEntry|false {
		if (isset($entry['permissions']) && is_int($entry['permissions']) && is_string($entry['path'])) {
			$entry['scan_permissions'] ??= $entry['permissions'];
			$entry['permissions'] &= $this->getACLPermissionsForPath($entry['path'], $rules);
			if (!$entry['permissions']) {
				return false;
			}
		}

		return $entry;
	}

	/**
	 * @return array<ICacheEntry|false>
	 */
	#[\Override]
	public function getFolderContentsById($fileId, ?string $mimeTypeFilter = null): array {
		$results = $this->getCache()->getFolderContentsById($fileId, $mimeTypeFilter);
		$rules = $this->preloadEntries($results);

		return array_filter(array_map(fn (ICacheEntry $entry): ICacheEntry|false => $this->formatCacheEntry($entry, $rules), $results));
	}

	#[\Override]
	public function search($pattern): array {
		$results = parent::search($pattern);
		$this->preloadEntries($results);

		return array_filter(array_map($this->formatCacheEntry(...), $results));
	}

	#[\Override]
	public function searchByMime($mimetype): array {
		$results = parent::searchByMime($mimetype);
		$this->preloadEntries($results);

		return array_filter(array_map($this->formatCacheEntry(...), $results));
	}

	#[\Override]
	public function searchQuery(ISearchQuery $query): array {
		$results = parent::searchQuery($query);
		$this->preloadEntries($results);

		return array_filter(array_map($this->formatCacheEntry(...), $results));
	}

	/**
	 * @param ICacheEntry[] $entries
	 * @return array<string, Rule[]>
	 */
	private function preloadEntries(array $entries): array {
		$paths = array_map(fn (ICacheEntry $entry): string => $entry->getPath(), $entries);

		return $this->aclManager->getRelevantRulesForPath($this->getNumericStorageId(), $paths, false);
	}

	/**
	 * Construct a search operator that filters out any paths that the current user doesn't have read permissions for
	 */
	private function getSearchFilter(): ISearchOperator {
		$forbiddenPaths = $this->aclManager->getForbiddenPaths($this->getNumericStorageId(), '');
		if (count($forbiddenPaths) === 0) {
			return new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, []);
		}

		$filters = array_map(fn (string $path): SearchBinaryOperator => new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [
			new SearchComparison(ISearchComparison::COMPARE_LIKE_CASE_SENSITIVE, 'path', SearchComparison::escapeLikeParameter($path) . '/%')
		]), $forbiddenPaths);
		$filters[] = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [
			new SearchComparison(ISearchComparison::COMPARE_IN, 'path', $forbiddenPaths)
		]);
		return new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, $filters);
	}

	#[\Override]
	public function getQueryFilterForStorage(): ISearchOperator {
		$storageFilter = parent::getQueryFilterForStorage();

		return new SearchBinaryOperator(
			ISearchBinaryOperator::OPERATOR_AND,
			[
				$storageFilter,
				$this->getSearchFilter(),
			]
		);
	}
}
