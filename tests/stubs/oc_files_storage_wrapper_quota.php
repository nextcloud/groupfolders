<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Filesystem;
use OC\SystemConfig;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\FileInfo;
use OCP\Files\Storage\IStorage;

class Quota extends Wrapper {
	/** @var callable|null */
	protected $quotaCallback;
	/** @var int|float|null int on 64bits, float on 32bits for bigint */
	protected int|float|null $quota;
	protected string $sizeRoot;

	/**
	 * @param array $parameters
	 */
	public function __construct($parameters)
 {
 }

	public function getQuota(): int|float
 {
 }

	/**
	 * @param string $path
	 * @param IStorage $storage
	 */
	protected function getSize($path, $storage = null): int|float
 {
 }

	public function free_space($path): int|float|false
 {
 }

	public function file_put_contents($path, $data): int|float|false
 {
 }

	public function copy($source, $target): bool
 {
 }

	public function fopen($path, $mode)
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath): bool
 {
 }

	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath): bool
 {
 }

	public function mkdir($path): bool
 {
 }

	public function touch($path, $mtime = null): bool
 {
 }
}
