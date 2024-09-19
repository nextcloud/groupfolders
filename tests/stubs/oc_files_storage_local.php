<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage;

use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Encryption;
use OC\Files\Storage\Wrapper\Jail;
use OCP\Constants;
use OCP\Files\ForbiddenException;
use OCP\Files\GenericFileException;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\Util;
use Psr\Log\LoggerInterface;

/**
 * for local filestore, we only have to map the paths
 */
class Local extends \OC\Files\Storage\Common {
	protected $datadir;

	protected $dataDirLength;

	protected $realDataDir;

	protected bool $unlinkOnTruncate;

	protected bool $caseInsensitive = false;

	public function __construct($arguments)
 {
 }

	public function __destruct() {
	}

	public function getId()
 {
 }

	public function mkdir($path)
 {
 }

	public function rmdir($path)
 {
 }

	public function opendir($path)
 {
 }

	public function is_dir($path)
 {
 }

	public function is_file($path)
 {
 }

	public function stat($path)
 {
 }

	public function getMetaData($path)
 {
 }

	public function filetype($path)
 {
 }

	public function filesize($path): false|int|float
 {
 }

	public function isReadable($path)
 {
 }

	public function isUpdatable($path)
 {
 }

	public function file_exists($path)
 {
 }

	public function filemtime($path)
 {
 }

	public function touch($path, $mtime = null)
 {
 }

	public function file_get_contents($path)
 {
 }

	public function file_put_contents($path, $data)
 {
 }

	public function unlink($path)
 {
 }

	public function rename($source, $target): bool
 {
 }

	public function copy($source, $target)
 {
 }

	public function fopen($path, $mode)
 {
 }

	public function hash($type, $path, $raw = false): string|false
 {
 }

	public function free_space($path)
 {
 }

	public function search($query)
 {
 }

	public function getLocalFile($path)
 {
 }

	/**
	 * @param string $query
	 * @param string $dir
	 * @return array
	 */
	protected function searchInDir($query, $dir = '')
 {
 }

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time)
 {
 }

	/**
	 * Get the source path (on disk) of a given path
	 *
	 * @param string $path
	 * @return string
	 * @throws ForbiddenException
	 */
	public function getSourcePath($path)
 {
 }

	/**
	 * {@inheritdoc}
	 */
	public function isLocal()
 {
 }

	public function getETag($path)
 {
 }

	/**
	 * @param IStorage $sourceStorage
	 * @param string $sourceInternalPath
	 * @param string $targetInternalPath
	 * @param bool $preserveMtime
	 * @return bool
	 */
	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false)
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

	public function writeStream(string $path, $stream, ?int $size = null): int
 {
 }
}
