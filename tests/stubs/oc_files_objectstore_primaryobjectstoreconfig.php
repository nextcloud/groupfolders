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
 * @psalm-type ObjectStoreConfig array{class: class-string<IObjectStore>, arguments: array{multibucket: bool, ...}}
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
}
