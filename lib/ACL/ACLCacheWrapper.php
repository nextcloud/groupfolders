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
	private ACLManager $aclManager;
	private bool $inShare;

	private function getACLPermissionsForPath(string $path, array $rules = []) {
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

	public function __construct(ICache $cache, ACLManager $aclManager, bool $inShare) {
		parent::__construct($cache);
		$this->aclManager = $aclManager;
		$this->inShare = $inShare;
	}

	protected function formatCacheEntry($entry, array $rules = []) {
		if (isset($entry['permissions'])) {
			$entry['scan_permissions'] = $entry['permissions'];
			$entry['permissions'] &= $this->getACLPermissionsForPath($entry['path'], $rules);
			if (!$entry['permissions']) {
				return false;
			}
		}
		return $entry;
	}

	public function getFolderContentsById($fileId) {
		$results = $this->getCache()->getFolderContentsById($fileId);
		$rules = $this->preloadEntries($results);
		$entries = array_map(function ($entry) use ($rules) {
			return $this->formatCacheEntry($entry, $rules);
		}, $results);
		return array_filter(array_filter($entries));
	}

	public function search($pattern) {
		$results = $this->getCache()->search($pattern);
		$this->preloadEntries($results);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	public function searchByMime($mimetype) {
		$results = $this->getCache()->searchByMime($mimetype);
		$this->preloadEntries($results);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	public function searchQuery(ISearchQuery $query) {
		$results = $this->getCache()->searchQuery($query);
		$this->preloadEntries($results);
		return array_map([$this, 'formatCacheEntry'], $results);
	}

	/**
	 * @param ICacheEntry[] $entries
	 * @return Rule[][]
	 */
	private function preloadEntries(array $entries): array {
		$paths = array_map(function (ICacheEntry $entry) {
			return $entry->getPath();
		}, $entries);
		return $this->aclManager->getRelevantRulesForPath($paths, false);
	}
}
