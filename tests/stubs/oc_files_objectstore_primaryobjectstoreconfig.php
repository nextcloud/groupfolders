<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OC\Files\ObjectStore;

use OCP\App\IAppManager;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\IConfig;
use OCP\IUser;

/**
 * @psalm-type ObjectStoreConfig array{class: class-string<IObjectStore>, arguments: array{multibucket: bool, objectPrefix?: string, ...}}
 */
class PrimaryObjectStoreConfig {
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppManager $appManager,
	) {
	}

	/**
	 * @param ObjectStoreConfig $config
	 */
	public function buildObjectStore(array $config): IObjectStore
 {
 }

	/**
	 * @return ?ObjectStoreConfig
	 */
	public function getObjectStoreConfigForRoot(): ?array
 {
 }

	/**
	 * @return ?ObjectStoreConfig
	 */
	public function getObjectStoreConfigForUser(IUser $user): ?array
 {
 }

	/**
	 * @param string $name
	 * @return ObjectStoreConfig
	 */
	public function getObjectStoreConfiguration(string $name): array
 {
 }

	public function resolveAlias(string $name): string
 {
 }

	public function hasObjectStore(): bool
 {
 }

	public function hasMultipleObjectStorages(): bool
 {
 }

	/**
	 * @return ?array<string, ObjectStoreConfig|string>
	 * @throws InvalidObjectStoreConfigurationException
	 */
	public function getObjectStoreConfigs(): ?array
 {
 }

	public function getBucketForUser(IUser $user, array $config): string
 {
 }

	public function getSetBucketForUser(IUser $user): ?string
 {
 }

	public function getObjectStoreForUser(IUser $user): string
 {
 }
}
