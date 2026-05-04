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
	public function __construct(
		private array $data,
	) {
	}

	#[\Override]
    public function offsetSet($offset, $value): void
    {
    }

	#[\Override]
    public function offsetExists($offset): bool
    {
    }

	#[\Override]
    public function offsetUnset($offset): void
    {
    }

	/**
	 * @return mixed
	 */
	#[\Override]
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
    }

	#[\Override]
    public function getId()
    {
    }

	#[\Override]
    public function getStorageId()
    {
    }


	#[\Override]
    public function getPath()
    {
    }


	#[\Override]
    public function getName()
    {
    }


	#[\Override]
    public function getMimeType(): string
    {
    }


	#[\Override]
    public function getMimePart()
    {
    }

	#[\Override]
    public function getSize()
    {
    }

	#[\Override]
    public function getMTime()
    {
    }

	#[\Override]
    public function getStorageMTime()
    {
    }

	#[\Override]
    public function getEtag()
    {
    }

	#[\Override]
    public function getPermissions(): int
    {
    }

	#[\Override]
    public function isEncrypted()
    {
    }

	#[\Override]
    public function getMetadataEtag(): ?string
    {
    }

	#[\Override]
    public function getCreationTime(): ?int
    {
    }

	#[\Override]
    public function getUploadTime(): ?int
    {
    }

	#[\Override]
    public function getParentId(): int
    {
    }

	public function getData()
    {
    }

	public function __clone()
    {
    }

	#[\Override]
    public function getUnencryptedSize(): int
    {
    }
}
