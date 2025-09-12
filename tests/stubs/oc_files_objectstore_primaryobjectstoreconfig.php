<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\ObjectStore;

/**
 * @psalm-type ObjectStoreConfig array{class: class-string<IObjectStore>, arguments: array{multibucket: bool, ...}}
 */
class PrimaryObjectStoreConfig
{
    public function __construct(private readonly \OCP\IConfig $config, private readonly \OCP\App\IAppManager $appManager)
    {
    }
    /**
     * @param ObjectStoreConfig $config
     */
    public function buildObjectStore(array $config): \OCP\Files\ObjectStore\IObjectStore
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
    public function getObjectStoreConfigForUser(\OCP\IUser $user): ?array
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
    public function getBucketForUser(\OCP\IUser $user, array $config): string
    {
    }
    public function getSetBucketForUser(\OCP\IUser $user): ?string
    {
    }
    public function getObjectStoreForUser(\OCP\IUser $user): string
    {
    }
}
