<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\ACL;

use OCP\ICache;

class MemoryCacheDecorator implements ICache
{
    const FALLBACK_TTL = 60;

    /**
     * @var ICache
     */
    protected $cache;

    /**
     * @var int
     */
    protected $ttl = self::FALLBACK_TTL;

    public function __construct(ICache $cache, int $ttl = self::FALLBACK_TTL)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    public function get($key)
    {
        return $this->cache->get($key);
    }

    public function set($key, $value, $ttl = 0)
    {
        if ($ttl === 0 || $ttl === null) {
            $ttl = $this->ttl;
        }

        return $this->cache->set($key, $value, $ttl);
    }

    public function hasKey($key)
    {
        return $this->cache->hasKey($key);
    }

    public function remove($key)
    {
        return $this->cache->remove($key);
    }

    public function clear($prefix = '')
    {
        return $this->cache->remove($prefix);
    }
}
