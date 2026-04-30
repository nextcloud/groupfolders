<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Versions\Versions;

use OCP\Files\FileInfo;
use OCP\IUser;

class Version implements IVersion, IMetadataVersion {
	public function __construct(
		private int $timestamp,
		private int|string $revisionId,
		private string $name,
		private int|float $size,
		private string $mimetype,
		private string $path,
		private FileInfo $sourceFileInfo,
		private IVersionBackend $backend,
		private IUser $user,
		private array $metadata = [],
	) {
	}

	#[\Override]
    public function getBackend(): IVersionBackend
    {
    }

	#[\Override]
    public function getSourceFile(): FileInfo
    {
    }

	#[\Override]
    public function getRevisionId()
    {
    }

	#[\Override]
    public function getTimestamp(): int
    {
    }

	#[\Override]
    public function getSize(): int|float
    {
    }

	#[\Override]
    public function getSourceFileName(): string
    {
    }

	#[\Override]
    public function getMimeType(): string
    {
    }

	#[\Override]
    public function getVersionPath(): string
    {
    }

	#[\Override]
    public function getUser(): IUser
    {
    }

	#[\Override]
    public function getMetadata(): array
    {
    }

	#[\Override]
    public function getMetadataValue(string $key): ?string
    {
    }
}
