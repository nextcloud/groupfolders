<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Scanner;
use OC\Files\ObjectStore\ObjectStoreScanner;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Quota;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\SubfolderQuota;
use OCA\GroupFolders\Folder\SubfolderQuotaManager;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IScanner;
use OCP\Files\FileInfo;
use OCP\Files\GenericFileException;
use OCP\Files\NotEnoughSpaceException;
use OCP\Files\Storage\IConstructableStorage;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\IUserSession;

class GroupFolderStorage extends Quota implements IConstructableStorage {
	private readonly FolderDefinition $folder;
	private readonly ?ICacheEntry $rootEntry;
	private readonly IUserSession $userSession;
	private readonly ?IUser $mountOwner;
	private readonly ?SubfolderQuotaManager $subfolderQuotaManager;
	private readonly bool $applySubfolderQuotas;

	/**
	 * @param array{
	 *     folder: FolderDefinition,
	 *     rootCacheEntry: ?ICacheEntry,
	 *     userSession: IUserSession,
	 *     mountOwner: ?IUser,
	 *     subfolderQuotaManager?: ?SubfolderQuotaManager,
	 *     applySubfolderQuotas?: bool,
	 * } $parameters
	 */
	public function __construct(array $parameters) {
		parent::__construct($parameters);
		$this->folder = $parameters['folder'];
		$this->rootEntry = $parameters['rootCacheEntry'];
		$this->userSession = $parameters['userSession'];
		$this->mountOwner = $parameters['mountOwner'];
		$this->subfolderQuotaManager = $parameters['subfolderQuotaManager'] ?? null;
		$this->applySubfolderQuotas = $parameters['applySubfolderQuotas'] ?? false;
	}

	public function getFolderId(): int {
		return $this->folder->id;
	}

	public function getFolder(): FolderDefinition {
		return $this->folder;
	}

	public function appliesSubfolderQuotas(): bool {
		return $this->applySubfolderQuotas;
	}

	#[\Override]
	public function getOwner(string $path): string|false {
		if ($this->mountOwner !== null) {
			return $this->mountOwner->getUID();
		}

		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $user->getUID();
		}

		return false;
	}

	public function getUser(): ?IUser {
		return $this->mountOwner;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getCache(string $path = '', ?IStorage $storage = null): ICache {
		if ($this->cache) {
			return $this->cache;
		}

		if (!$storage) {
			$storage = $this;
		}

		$cache = parent::getCache($path, $storage);
		if ($this->rootEntry !== null) {
			$cache = new RootEntryCache($cache, $this->rootEntry);
		}
		$this->cache = $cache;

		return $this->cache;
	}

	/**
	 * @inheritDoc
	 * @param string $path
	 * @param ?IStorage $storage
	 */
	#[\Override]
	public function getScanner($path = '', $storage = null): IScanner {
		/** @var ?\OC\Files\Storage\Wrapper\Wrapper $storage */
		if (!$storage) {
			$storage = $this;
		}

		/** @phpstan-ignore method.impossibleType */
		if ($storage->instanceOfStorage(ObjectStoreStorage::class)) {
			$storage->scanner = new ObjectStoreScanner($storage);
		} elseif (!isset($storage->scanner)) {
			$storage->scanner = new Scanner($storage);
		}

		return $storage->scanner;
	}

	#[\Override]
	protected function shouldApplyQuota(string $path): bool {
		return true;
	}

	/**
	 * Return the most restrictive amount of free space from the Team folder and
	 * the configured direct subfolder, if this path belongs to one.
	 */
	#[\Override]
	public function free_space(string $path): int|float|false {
		$freeSpace = parent::free_space($path);
		$subfolderFreeSpace = $this->getSubfolderFreeSpace($path);
		if ($subfolderFreeSpace === null || $subfolderFreeSpace < 0) {
			return $freeSpace;
		}
		if ($freeSpace === false) {
			return false;
		}

		return $freeSpace >= 0 ? min($freeSpace, $subfolderFreeSpace) : $subfolderFreeSpace;
	}

	/**
	 * The core Quota wrapper does not execute its checks when the Team folder
	 * itself is unlimited. Apply the same checks explicitly for a path that is
	 * covered by a subfolder quota in that case.
	 */
	#[\Override]
	public function file_put_contents(string $path, mixed $data): int|float|false {
		if (!$this->needsStandaloneSubfolderQuota($path) || !is_string($data)) {
			return parent::file_put_contents($path, $data);
		}

		$free = $this->free_space($path);
		if ($free === false) {
			return false;
		}
		if ($free < 0 || strlen($data) < $free) {
			return $this->getWrapperStorage()->file_put_contents($path, $data);
		}

		return false;
	}

	#[\Override]
	public function copy(string $source, string $target): bool {
		if (!$this->needsStandaloneSubfolderQuota($target)) {
			return parent::copy($source, $target);
		}

		$free = $this->free_space($target);
		if ($free === false) {
			return false;
		}
		if ($free < 0 || $this->getSize($source) < $free) {
			return $this->getWrapperStorage()->copy($source, $target);
		}

		return false;
	}

	#[\Override]
	public function fopen(string $path, string $mode) {
		if (!$this->needsStandaloneSubfolderQuota($path) || $this->isPartFile($path)) {
			return parent::fopen($path, $mode);
		}
		if ($mode === 'r' || $mode === 'rb') {
			return $this->getWrapperStorage()->fopen($path, $mode);
		}

		$free = $this->free_space($path);
		if ($free === false || $free === 0) {
			return false;
		}

		$source = $this->getWrapperStorage()->fopen($path, $mode);
		if (!is_resource($source)) {
			return false;
		}
		if ($free >= 0) {
			return \OC\Files\Stream\Quota::wrap($source, $free);
		}

		return $source;
	}

	#[\Override]
	public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		if (!$this->needsStandaloneSubfolderQuota($targetInternalPath)) {
			return parent::copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		}

		$free = $this->free_space($targetInternalPath);
		if ($free === false) {
			return false;
		}
		if ($free < 0 || $this->getSize($sourceInternalPath, $sourceStorage) < $free) {
			return $this->getWrapperStorage()->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		}

		return false;
	}

	#[\Override]
	public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		if ($sourceStorage === $this) {
			return $this->rename($sourceInternalPath, $targetInternalPath);
		}

		if (!$this->needsStandaloneSubfolderQuota($targetInternalPath)) {
			return parent::moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		}

		$free = $this->free_space($targetInternalPath);
		if ($free === false) {
			return false;
		}
		if ($free < 0 || $this->getSize($sourceInternalPath, $sourceStorage) < $free) {
			return $this->getWrapperStorage()->moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
		}

		return false;
	}

	#[\Override]
	public function mkdir(string $path): bool {
		if (!$this->needsStandaloneSubfolderQuota($path)) {
			return parent::mkdir($path);
		}

		$free = $this->free_space($path);
		return $free !== false && $free !== 0 && $this->getWrapperStorage()->mkdir($path);
	}

	#[\Override]
	public function touch(string $path, ?int $mtime = null): bool {
		if (!$this->needsStandaloneSubfolderQuota($path)) {
			return parent::touch($path, $mtime);
		}

		$free = $this->free_space($path);
		return $free !== false && $free !== 0 && $this->getWrapperStorage()->touch($path, $mtime);
	}

	#[\Override]
	public function writeStream(string $path, $stream, ?int $size = null): int {
		if (!$this->needsStandaloneSubfolderQuota($path)) {
			return parent::writeStream($path, $stream, $size);
		}

		$free = $this->free_space($path);
		if ($free === 0 || $free === false) {
			throw new NotEnoughSpaceException();
		}

		if ($size !== null) {
			if ($free < 0 || $size < $free) {
				return parent::writeStream($path, $stream, $size);
			}
			throw new NotEnoughSpaceException();
		}

		try {
			return parent::writeStreamFallback($path, $stream);
		} catch (GenericFileException) {
			throw new NotEnoughSpaceException();
		}
	}

	#[\Override]
	public function rename(string $source, string $target): bool {
		if (!$this->applySubfolderQuotas || $this->subfolderQuotaManager === null) {
			return parent::rename($source, $target);
		}

		$sourceQuota = $this->getSubfolderQuota($source);
		if ($sourceQuota !== null && $this->isDirectChildPath($source) && !$this->isDirectChildPath($target)) {
			return false;
		}

		$targetQuota = $this->getSubfolderQuota($target);
		if ($targetQuota === null || $targetQuota->fileId === $sourceQuota?->fileId) {
			return parent::rename($source, $target);
		}

		$sourceSize = $this->getSize($source);
		$targetFreeSpace = $this->getFreeSpaceForQuota($targetQuota);
		if ($sourceSize < 0 || $targetFreeSpace < 0 || $sourceSize < $targetFreeSpace) {
			return parent::rename($source, $target);
		}

		return false;
	}

	private function needsStandaloneSubfolderQuota(string $path): bool {
		return $this->getQuota() === FileInfo::SPACE_UNLIMITED && $this->getSubfolderQuota($path) !== null;
	}

	private function getSubfolderQuota(string $path): ?SubfolderQuota {
		if (!$this->applySubfolderQuotas || $this->subfolderQuotaManager === null) {
			return null;
		}

		return $this->subfolderQuotaManager->getQuotaForPath($this->folder, $this->getCache(), $path);
	}

	private function getSubfolderFreeSpace(string $path): ?int {
		$quota = $this->getSubfolderQuota($path);
		if ($quota === null) {
			return null;
		}

		return $this->getFreeSpaceForQuota($quota);
	}

	private function getFreeSpaceForQuota(SubfolderQuota $quota): int {
		if ($quota->size < 0) {
			return FileInfo::SPACE_NOT_COMPUTED;
		}

		return max($quota->quota - $quota->size, 0);
	}

	private function isDirectChildPath(string $path): bool {
		$path = trim($path, '/');
		return $path !== '' && !str_contains($path, '/');
	}

	private function isPartFile(string $path): bool {
		return pathinfo($path, PATHINFO_EXTENSION) === 'part';
	}
}
