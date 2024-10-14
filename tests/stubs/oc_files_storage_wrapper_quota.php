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

	protected function getSize(string $path, ?IStorage $storage = null): int|float
 {
 }

	public function free_space(string $path): int|float|false
 {
 }

	public function file_put_contents(string $path, mixed $data): int|float|false
 {
 }

	public function copy(string $source, string $target): bool
 {
 }

	public function fopen(string $path, string $mode)
 {
 }

	/**
	 * Only apply quota for files, not metadata, trash or others
	 */
	protected function shouldApplyQuota(string $path): bool
 {
 }

	public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool
 {
 }

	public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool
 {
 }

	public function mkdir(string $path): bool
 {
 }

	public function touch(string $path, ?int $mtime = null): bool
 {
 }
}
