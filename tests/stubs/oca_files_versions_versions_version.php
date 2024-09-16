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

	public function getBackend(): IVersionBackend
 {
 }

	public function getSourceFile(): FileInfo
 {
 }

	public function getRevisionId()
 {
 }

	public function getTimestamp(): int
 {
 }

	public function getSize(): int|float
 {
 }

	public function getSourceFileName(): string
 {
 }

	public function getMimeType(): string
 {
 }

	public function getVersionPath(): string
 {
 }

	public function getUser(): IUser
 {
 }

	public function getMetadata(): array
 {
 }

	public function getMetadataValue(string $key): ?string
 {
 }
}
