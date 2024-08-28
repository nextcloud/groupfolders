<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Wrapper\CacheJail;
use OC\Files\Storage\Wrapper\Jail;

/**
 * Jail with overridden behaviors specific to group folders when encryption is
 * enabled.
 */
class GroupFolderEncryptionJail extends Jail {
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this->getWrapperStorage();
		}
		// By default the Jail reuses the inner cache, but when encryption is
		// enabled the storage needs to be passed to the cache so it takes into
		// account the outer Encryption wrapper.
		$sourceCache = $this->getWrapperStorage()->getCache($this->getUnjailedPath($path), $storage);
		return new CacheJail($sourceCache, $this->rootPath);
	}
}
