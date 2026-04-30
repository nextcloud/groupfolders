<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\ObjectStore;

use OC\Files\Cache\Scanner;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\FileInfo;

class ObjectStoreScanner extends Scanner {
	#[\Override]
    public function scanFile($file, $reuseExisting = 0, $parentId = -1, $cacheData = null, $lock = true, $data = null)
    {
    }

	#[\Override]
    public function scan($path, $recursive = self::SCAN_RECURSIVE, $reuse = -1, $lock = true)
    {
    }

	#[\Override]
    protected function scanChildren(string $path, $recursive, int $reuse, int $folderId, bool $lock, int|float $oldSize, &$etagChanged = false)
    {
    }

	#[\Override]
    public function backgroundScan()
    {
    }
}
