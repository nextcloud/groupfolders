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

	public function isUpdatable($path)
 {
 }

	public function isCreatable($path)
 {
 }

	public function isDeletable($path)
 {
 }

	public function isSharable($path)
 {
 }

	public function getPermissions($path)
 {
 }

	public function rename($source, $target)
 {
 }

	public function copy($source, $target)
 {
 }

	public function touch($path, $mtime = null)
 {
 }

	public function mkdir($path)
 {
 }

	public function rmdir($path)
 {
 }

	public function unlink($path)
 {
 }

	public function file_put_contents($path, $data)
 {
 }

	public function fopen($path, $mode)
 {
 }

	public function getCache($path = '', $storage = null)
 {
 }

	public function getMetaData($path)
 {
 }

	public function getScanner($path = '', $storage = null)
 {
 }

	public function getDirectoryContent($directory): \Traversable
 {
 }
}
