<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test;

use OC\App\AppStore\Fetcher\AppFetcher;
use OC\Command\QueueBus;
use OC\Files\AppData\Factory;
use OC\Files\Cache\Storage;
use OC\Files\Config\MountProviderCollection;
use OC\Files\Config\UserMountCache;
use OC\Files\Filesystem;
use OC\Files\Mount\CacheMountProvider;
use OC\Files\Mount\LocalHomeMountProvider;
use OC\Files\Mount\RootMountProvider;
use OC\Files\ObjectStore\PrimaryObjectStoreConfig;
use OC\Files\SetupManager;
use OC\Files\View;
use OC\Installer;
use OC\Updater;
use OCP\Command\IBus;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Security\ISecureRandom;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;
use Psr\Container\ContainerExceptionInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase {
	protected static ?IDBConnection $realDatabase = null;
	protected array $services = [];

	protected function onNotSuccessfulTest(\Throwable $t): never
 {
 }

	public function overwriteService(string $name, mixed $newService): bool
 {
 }

	public function restoreService(string $name): bool
 {
 }

	public function restoreAllServices(): void
 {
 }

	protected function getTestTraits(): array
 {
 }

	protected function setUp(): void
 {
 }

	protected function tearDown(): void
 {
 }

	/**
	 * Allows us to test private methods/properties
	 *
	 * @param $object
	 * @param $methodName
	 * @param array $parameters
	 * @return mixed
	 */
	protected static function invokePrivate($object, $methodName, array $parameters = [])
 {
 }

	/**
	 * Returns a unique identifier as uniqid() is not reliable sometimes
	 *
	 * @param string $prefix
	 * @param int $length
	 * @return string
	 */
	protected static function getUniqueID($prefix = '', $length = 13)
 {
 }

	/**
	 * Filter methods
	 *
	 * Returns all methods of the given class,
	 * that are public or abstract and not in the ignoreMethods list,
	 * to be able to fill onlyMethods() with an inverted list.
	 *
	 * @param string $className
	 * @param string[] $filterMethods
	 * @return string[]
	 */
	public function filterClassMethods(string $className, array $filterMethods): array
 {
 }

	public static function tearDownAfterClass(): void
 {
 }

	/**
	 * Remove all entries from the share table
	 */
	protected static function tearDownAfterClassCleanShares(IQueryBuilder $queryBuilder): void
 {
 }

	/**
	 * Remove all entries from the storages table
	 */
	protected static function tearDownAfterClassCleanStorages(IQueryBuilder $queryBuilder): void
 {
 }

	/**
	 * Remove all entries from the filecache table
	 */
	protected static function tearDownAfterClassCleanFileCache(IQueryBuilder $queryBuilder): void
 {
 }

	/**
	 * Remove all unused files from the data dir
	 *
	 * @param string $dataDir
	 */
	protected static function tearDownAfterClassCleanStrayDataFiles(string $dataDir): void
 {
 }

	/**
	 * Recursive delete files and folders from a given directory
	 *
	 * @param string $dir
	 */
	protected static function tearDownAfterClassCleanStrayDataUnlinkDir(string $dir): void
 {
 }

	/**
	 * Clean up the list of hooks
	 */
	protected static function tearDownAfterClassCleanStrayHooks(): void
 {
 }

	/**
	 * Clean up the list of locks
	 */
	protected static function tearDownAfterClassCleanStrayLocks(): void
 {
 }

	/**
	 * Login and setup FS as a given user,
	 * sets the given user as the current user.
	 *
	 * @param string $user user id or empty for a generic FS
	 */
	protected static function loginAsUser(string $user = ''): void
 {
 }

	/**
	 * Logout the current user and tear down the filesystem.
	 */
	protected static function logout(): void
 {
 }

	/**
	 * Run all commands pushed to the bus
	 */
	protected function runCommands(): void
 {
 }

	/**
	 * Check if the given path is locked with a given type
	 *
	 * @param View $view view
	 * @param string $path path to check
	 * @param int $type lock type
	 * @param bool $onMountPoint true to check the mount point instead of the
	 *                           mounted storage
	 *
	 * @return boolean true if the file is locked with the
	 *                 given type, false otherwise
	 */
	protected function isFileLocked(View $view, string $path, int $type, bool $onMountPoint = false)
 {
 }

	/**
	 * @return list<string>
	 */
	protected function getGroupAnnotations(): array
 {
 }

	protected function IsDatabaseAccessAllowed(): bool
 {
 }
}
