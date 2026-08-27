<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

/**
 * A direct child folder and its optional independent quota.
 */
class SubfolderQuota {
	public function __construct(
		public readonly int $fileId,
		public readonly string $name,
		public readonly int $size,
		public readonly ?int $quota,
	) {
	}

	/**
	 * @return array{file_id: int, name: string, size: int, quota: ?int}
	 */
	public function toArray(): array {
		return [
			'file_id' => $this->fileId,
			'name' => $this->name,
			'size' => $this->size,
			'quota' => $this->quota,
		];
	}
}
