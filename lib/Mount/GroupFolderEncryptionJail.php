<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
