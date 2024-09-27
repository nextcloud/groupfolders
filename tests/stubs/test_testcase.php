<?php
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test;

use DOMDocument;
use DOMNode;
use OC\Command\QueueBus;
use OC\Files\Config\MountProviderCollection;
use OC\Files\Filesystem;
use OC\Files\Mount\CacheMountProvider;
use OC\Files\Mount\LocalHomeMountProvider;
use OC\Files\Mount\RootMountProvider;
use OC\Files\SetupManager;
use OC\Template\Base;
use OCP\Command\IBus;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Lock\ILockingProvider;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

if (version_compare(\PHPUnit\Runner\Version::id(), 10, '>=')) {
	trait OnNotSuccessfulTestTrait {
		protected function onNotSuccessfulTest(\Throwable $t): never {
			$this->restoreAllServices();

			// restore database connection
			if (!$this->IsDatabaseAccessAllowed()) {
				\OC::$server->registerService(IDBConnection::class, function () {
					return self::$realDatabase;
				});
			}

			parent::onNotSuccessfulTest($t);
		}
	}
} else {
	trait OnNotSuccessfulTestTrait {
		protected function onNotSuccessfulTest(\Throwable $t): void {
			$this->restoreAllServices();

			// restore database connection
			if (!$this->IsDatabaseAccessAllowed()) {
				\OC::$server->registerService(IDBConnection::class, function () {
					return self::$realDatabase;
				});
			}

			parent::onNotSuccessfulTest($t);
		}
	}
}

abstract class TestCase extends \PHPUnit\Framework\TestCase {
	/** @var IDBConnection */
	protected static $realDatabase = null;

	/** @var array */
	protected $services = [];

	use OnNotSuccessfulTestTrait;

	/**
	 * @param string $name
	 * @param mixed $newService
	 * @return bool
	 */
	public function overwriteService(string $name, $newService): bool
 {
 }

	/**
	 * @param string $name
	 * @return bool
	 */
	public function restoreService(string $name): bool
 {
 }

	public function restoreAllServices()
 {
 }

	protected function getTestTraits()
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
	 *
	 * @param IQueryBuilder $queryBuilder
	 */
	protected static function tearDownAfterClassCleanShares(IQueryBuilder $queryBuilder)
 {
 }

	/**
	 * Remove all entries from the storages table
	 *
	 * @param IQueryBuilder $queryBuilder
	 */
	protected static function tearDownAfterClassCleanStorages(IQueryBuilder $queryBuilder)
 {
 }

	/**
	 * Remove all entries from the filecache table
	 *
	 * @param IQueryBuilder $queryBuilder
	 */
	protected static function tearDownAfterClassCleanFileCache(IQueryBuilder $queryBuilder)
 {
 }

	/**
	 * Remove all unused files from the data dir
	 *
	 * @param string $dataDir
	 */
	protected static function tearDownAfterClassCleanStrayDataFiles($dataDir)
 {
 }

	/**
	 * Recursive delete files and folders from a given directory
	 *
	 * @param string $dir
	 */
	protected static function tearDownAfterClassCleanStrayDataUnlinkDir($dir)
 {
 }

	/**
	 * Clean up the list of hooks
	 */
	protected static function tearDownAfterClassCleanStrayHooks()
 {
 }

	/**
	 * Clean up the list of locks
	 */
	protected static function tearDownAfterClassCleanStrayLocks()
 {
 }

	/**
	 * Login and setup FS as a given user,
	 * sets the given user as the current user.
	 *
	 * @param string $user user id or empty for a generic FS
	 */
	protected static function loginAsUser($user = '')
 {
 }

	/**
	 * Logout the current user and tear down the filesystem.
	 */
	protected static function logout()
 {
 }

	/**
	 * Run all commands pushed to the bus
	 */
	protected function runCommands()
 {
 }

	/**
	 * Check if the given path is locked with a given type
	 *
	 * @param \OC\Files\View $view view
	 * @param string $path path to check
	 * @param int $type lock type
	 * @param bool $onMountPoint true to check the mount point instead of the
	 *                           mounted storage
	 *
	 * @return boolean true if the file is locked with the
	 *                 given type, false otherwise
	 */
	protected function isFileLocked($view, $path, $type, $onMountPoint = false)
 {
 }

	protected function getGroupAnnotations(): array
 {
 }

	protected function IsDatabaseAccessAllowed()
 {
 }

	/**
	 * @param string $expectedHtml
	 * @param string $template
	 * @param array $vars
	 */
	protected function assertTemplate($expectedHtml, $template, $vars = [])
 {
 }

	/**
	 * @param string $expectedHtml
	 * @param string $actualHtml
	 * @param string $message
	 */
	protected function assertHtmlStringEqualsHtmlString($expectedHtml, $actualHtml, $message = '')
 {
 }
}
