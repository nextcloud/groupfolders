<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage\Wrapper;

use OC\Files\Cache\Wrapper\CachePermissionsMask;
use OCP\Constants;

/**
 * Mask the permissions of a storage
 *
 * This can be used to restrict update, create, delete and/or share permissions of a storage
 *
 * Note that the read permissions can't be masked
 */
class PermissionsMask extends Wrapper {
	/**
	 * @param array $arguments ['storage' => $storage, 'mask' => $mask]
	 *
	 * $storage: The storage the permissions mask should be applied on
	 * $mask: The permission bits that should be kept, a combination of the \OCP\Constant::PERMISSION_ constants
	 */
	public function __construct($arguments)
 {
 }

	public function isUpdatable($path): bool
 {
 }

	public function isCreatable($path): bool
 {
 }

	public function isDeletable($path): bool
 {
 }

	public function isSharable($path): bool
 {
 }

	public function getPermissions($path): int
 {
 }

	public function rename($source, $target): bool
 {
 }

	public function copy($source, $target): bool
 {
 }

	public function touch($path, $mtime = null): bool
 {
 }

	public function mkdir($path): bool
 {
 }

	public function rmdir($path): bool
 {
 }

	public function unlink($path): bool
 {
 }

	public function file_put_contents($path, $data): int|float|false
 {
 }

	public function fopen($path, $mode)
 {
 }

	public function getCache($path = '', $storage = null): \OCP\Files\Cache\ICache
 {
 }

	public function getMetaData($path): ?array
 {
 }

	public function getScanner($path = '', $storage = null): \OCP\Files\Cache\IScanner
 {
 }

	public function getDirectoryContent($directory): \Traversable
 {
 }
}
