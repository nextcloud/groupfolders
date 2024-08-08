<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Versions;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method int|float getSize()
 * @method void setSize(int|float $size)
 * @method int getMimetype()
 * @method void setMimetype(int $mimetype)
 * @method string getMetadata()
 * @method void setMetadata(string $metadata)
 */
class GroupVersionEntity extends Entity implements JsonSerializable {
	protected ?int $fileId = null;
	protected ?int $timestamp = null;
	protected ?int $size = null;
	protected ?int $mimetype = null;
	protected ?string $metadata = null;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('file_id', Types::INTEGER);
		$this->addType('timestamp', Types::INTEGER);
		$this->addType('size', Types::INTEGER);
		$this->addType('mimetype', Types::INTEGER);
		$this->addType('metadata', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'file_id' => $this->fileId,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'mimetype' => $this->mimetype,
			'metadata' => $this->metadata,
		];
	}

	public function getDecodedMetadata(): array {
		return json_decode($this->metadata ?? '', true, 512, JSON_THROW_ON_ERROR) ?? [];
	}

	public function setDecodedMetadata(array $value): void {
		$this->metadata = json_encode($value, JSON_THROW_ON_ERROR);
		$this->markFieldUpdated('metadata');
	}

	/**
	 * @abstract given a key, return the value associated with the key in the metadata column
	 * if nothing is found, we return an empty string
	 * @param string $key key associated with the value
	 */
	public function getMetadataValue(string $key): ?string {
		return $this->getDecodedMetadata()[$key] ?? null;
	}

	/**
	 * @abstract sets a key value pair in the metadata column
	 * @param string $key key associated with the value
	 * @param string $value value associated with the key
	 */
	public function setMetadataValue(string $key, string $value): void {
		$metadata = $this->getDecodedMetadata();
		$metadata[$key] = $value;
		$this->setDecodedMetadata($metadata);
		$this->markFieldUpdated('metadata');
	}
}
