<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\DB\QueryBuilder\Sharded;

use OCP\DB\QueryBuilder\Sharded\IShardMapper;

/**
 * Configuration for a shard setup
 */
class ShardDefinition {
	// we reserve the bottom byte of the primary key for the initial shard, so the total shard count is limited to what we can fit there
	// additionally, shard id 255 is reserved for migration purposes
	public const MAX_SHARDS = 255;
	public const MIGRATION_SHARD = 255;

	public const PRIMARY_KEY_MASK = 0x7F_FF_FF_FF_FF_FF_FF_00;
	public const PRIMARY_KEY_SHARD_MASK = 0x00_00_00_00_00_00_00_FF;
	// since we reserve 1 byte for the shard index, we only have 56 bits of primary key space
	public const MAX_PRIMARY_KEY = PHP_INT_MAX >> 8;

	/**
	 * @param string $table
	 * @param string $primaryKey
	 * @param string $shardKey
	 * @param string[] $companionKeys
	 * @param IShardMapper $shardMapper
	 * @param string[] $companionTables
	 * @param array $shards
	 */
	public function __construct(public string $table, public string $primaryKey, public array $companionKeys, public string $shardKey, public IShardMapper $shardMapper, public array $companionTables, public array $shards, public int $fromFileId, public int $fromStorageId)
 {
 }

	public function hasTable(string $table): bool
 {
 }

	public function getShardForKey(int $key): int
 {
 }

	/**
	 * @return list<int>
	 */
	public function getAllShards(): array
 {
 }

	public function isKey(string $column): bool
 {
 }
}
