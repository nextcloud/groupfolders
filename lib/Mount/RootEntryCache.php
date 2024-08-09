<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Wrapper\CacheWrapper;
use OCP\Files\Cache\ICacheEntry;

class RootEntryCache extends CacheWrapper {
	/** @var ICacheEntry|null */
	private $rootEntry;

	public function __construct($cache, ?ICacheEntry $rootEntry = null) {
		parent::__construct($cache);
		$this->rootEntry = $rootEntry;
	}

	public function get($file) {
		if ($file === '' && $this->rootEntry) {
			return $this->rootEntry;
		}
		return parent::get($file);
	}

	public function getId($file) {
		if ($file === '' && $this->rootEntry) {
			return $this->rootEntry->getId();
		}
		return parent::getId($file);
	}

	public function update($id, array $data) {
		$this->rootEntry = null;
		parent::update($id, $data);
	}

	public function insert($file, array $data) {
		$this->rootEntry = null;
		return parent::insert($file, $data);
	}
}
