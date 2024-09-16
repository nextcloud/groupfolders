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

	/**
	 * @return int|float quota value
	 */
	public function getQuota(): int|float
 {
 }

	/**
	 * @param string $path
	 * @param IStorage $storage
	 * @return int|float
	 */
	protected function getSize($path, $storage = null)
 {
 }

	/**
	 * Get free space as limited by the quota
	 *
	 * @param string $path
	 * @return int|float|bool
	 */
	public function free_space($path)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.file_put_contents.php
	 *
	 * @param string $path
	 * @param mixed $data
	 * @return int|float|false
	 */
	public function file_put_contents($path, $data)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.copy.php
	 *
	 * @param string $source
	 * @param string $target
	 * @return bool
	 */
	public function copy($source, $target)
 {
 }

	/**
	 * see https://www.php.net/manual/en/function.fopen.php
	 *
	 * @param string $path
	 * @param string $mode
	 * @return resource|bool
	 */
	public function fopen($path, $mode)
 {
 }

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath)
 {
 }

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @return bool
	 */
	public function moveFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath)
 {
 }

	public function mkdir($path)
 {
 }

	public function touch($path, $mtime = null)
 {
 }
}
