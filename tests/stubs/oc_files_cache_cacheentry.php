<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Cache;

use OCP\Files\Cache\ICacheEntry;

/**
 * meta data for a file or folder
 */
class CacheEntry implements ICacheEntry {
	public function __construct(array $data)
 {
 }

	public function offsetSet($offset, $value): void
 {
 }

	public function offsetExists($offset): bool
 {
 }

	public function offsetUnset($offset): void
 {
 }

	/**
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
 public function offsetGet($offset)
 {
 }

	public function getId()
 {
 }

	public function getStorageId()
 {
 }


	public function getPath()
 {
 }


	public function getName()
 {
 }


	public function getMimeType(): string
 {
 }


	public function getMimePart()
 {
 }

	public function getSize()
 {
 }

	public function getMTime()
 {
 }

	public function getStorageMTime()
 {
 }

	public function getEtag()
 {
 }

	public function getPermissions()
 {
 }

	public function isEncrypted()
 {
 }

	public function getMetadataEtag(): ?string
 {
 }

	public function getCreationTime(): ?int
 {
 }

	public function getUploadTime(): ?int
 {
 }

	public function getParentId(): int
 {
 }

	public function getData()
 {
 }

	public function __clone()
 {
 }

	public function getUnencryptedSize(): int
 {
 }
}
