<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OC\Files\Cache\Wrapper\CacheWrapper;
use OCP\Constants;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Search\ISearchQuery;

class ACLCacheWrapper extends CacheWrapper {
	public function __construct(
		ICache $cache,
		private ACLManager $aclManager,
		private bool $inShare,
	) {
		parent::__construct($cache);
	}

	private function getACLPermissionsForPath(string $path, array $rules = []): int {
		if ($rules) {
			$permissions = $this->aclManager->getPermissionsForPathFromRules($path, $rules);
		} else {
			$permissions = $this->aclManager->getACLPermissionsForPath($path);
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

	protected function formatCacheEntry($entry, array $rules = []): ICacheEntry|false {
		if (isset($entry['permissions'])) {
			$entry['scan_permissions'] ??= $entry['permissions'];
			$entry['permissions'] &= $this->getACLPermissionsForPath($entry['path'], $rules);
			if (!$entry['permissions']) {
				return false;
			}
		}

		return $entry;
	}

	public function getFolderContentsById($fileId): array {
		$results = $this->getCache()->getFolderContentsById($fileId);
		$rules = $this->preloadEntries($results);

		return array_filter(array_map(fn (ICacheEntry $entry): ICacheEntry|false => $this->formatCacheEntry($entry, $rules), $results));
	}

	public function search($pattern): array {
		$results = $this->getCache()->search($pattern);
		$this->preloadEntries($results);

		return array_filter(array_map($this->formatCacheEntry(...), $results));
	}

	public function searchByMime($mimetype): array {
		$results = $this->getCache()->searchByMime($mimetype);
		$this->preloadEntries($results);

		return array_filter(array_map($this->formatCacheEntry(...), $results));
	}

	public function searchQuery(ISearchQuery $query): array {
		$results = $this->getCache()->searchQuery($query);
		$this->preloadEntries($results);

		return array_filter(array_map($this->formatCacheEntry(...), $results));
	}

	/**
	 * @param ICacheEntry[] $entries
	 * @return array<string, Rule[]>
	 */
	private function preloadEntries(array $entries): array {
		$paths = array_map(fn (ICacheEntry $entry): string => $entry->getPath(), $entries);

		return $this->aclManager->getRelevantRulesForPath($paths, false);
	}
}
