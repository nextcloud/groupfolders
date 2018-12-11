<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

use OC\Files\Cache\Wrapper\CacheWrapper;
use OCP\Files\Cache\ICacheEntry;

class RootEntryCache extends CacheWrapper {
	/** @var ICacheEntry|null */
	private $rootEntry;

	public function __construct($cache, ICacheEntry $rootEntry = null) {
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
