<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
use OC\App\AppManager;
use OC\App\DependencyAnalyzer;
use OC\AppFramework\Bootstrap\Coordinator;
use OC\Installer;
use OC\Repair;
use OC\Repair\Events\RepairErrorEvent;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\Authentication\IAlternativeLogin;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;
use function OCP\Log\logger;

/**
 * This class manages the apps. It allows them to register and integrate in the
 * Nextcloud ecosystem. Furthermore, this class is responsible for installing,
 * upgrading and removing apps.
 */
class OC_App {
	public const supportedApp = 300;
	public const officialApp = 200;

	/**
	 * clean the appId
	 *
	 * @psalm-taint-escape file
	 * @psalm-taint-escape include
	 * @psalm-taint-escape html
	 * @psalm-taint-escape has_quotes
	 *
	 * @deprecated 31.0.0 use IAppManager::cleanAppId
	 */
	public static function cleanAppId(string $app): string
    {
    }

	/**
	 * Check if an app is loaded
	 *
	 * @param string $app
	 * @return bool
	 * @deprecated 27.0.0 use IAppManager::isAppLoaded
	 */
	public static function isAppLoaded(string $app): bool
    {
    }

	/**
	 * loads all apps
	 *
	 * @param string[] $types
	 * @return bool
	 *
	 * This function walks through the Nextcloud directory and loads all apps
	 * it can find. A directory contains an app if the file /appinfo/info.xml
	 * exists.
	 *
	 * if $types is set to non-empty array, only apps of those types will be loaded
	 *
	 * @deprecated 29.0.0 use IAppManager::loadApps instead
	 */
	public static function loadApps(array $types = []): bool
    {
    }

	/**
	 * load a single app
	 *
	 * @param string $app
	 * @throws Exception
	 * @deprecated 27.0.0 use IAppManager::loadApp
	 */
	public static function loadApp(string $app): void
    {
    }

	/**
	 * @internal
	 * @param string $app
	 * @param string $path
	 * @param bool $force
	 */
	public static function registerAutoloading(string $app, string $path, bool $force = false)
    {
    }

	/**
	 * check if an app is of a specific type
	 *
	 * @param string $app
	 * @param array $types
	 * @return bool
	 * @deprecated 27.0.0 use IAppManager::isType
	 */
	public static function isType(string $app, array $types): bool
    {
    }

	/**
	 * read app types from info.xml and cache them in the database
	 */
	public static function setAppTypes(string $app)
    {
    }

	/**
	 * Returns apps enabled for the current user.
	 *
	 * @param bool $forceRefresh whether to refresh the cache
	 * @param bool $all whether to return apps for all users, not only the
	 *                  currently logged in one
	 * @return list<string>
	 */
	public static function getEnabledApps(bool $forceRefresh = false, bool $all = false): array
    {
    }

	/**
	 * enables an app
	 *
	 * @param string $appId
	 * @param array $groups (optional) when set, only these groups will have access to the app
	 * @throws \Exception
	 * @return void
	 * @deprecated 32.0.0 Use the installer and the app manager instead
	 *
	 * This function set an app as enabled in appconfig.
	 */
	public function enable(string $appId, array $groups = [])
    {
    }

	/**
	 * Find the apps root for an app id.
	 *
	 * If multiple copies are found, the apps root the latest version is returned.
	 *
	 * @param bool $ignoreCache ignore cache and rebuild it
	 * @return false|array{path: string, url: string} the apps root shape
	 * @deprecated 32.0.0 internal, use getAppPath or getAppWebPath
	 */
	public static function findAppInDirectories(string $appId, bool $ignoreCache = false)
    {
    }

	/**
	 * get app's version based on it's path
	 *
	 * @deprecated 32.0.0 use Server::get(IAppManager)->getAppInfoByPath() with the path to info.xml directly
	 */
	public static function getAppVersionByPath(string $path): string
    {
    }

	/**
	 * get the id of loaded app
	 *
	 * @return string
	 */
	public static function getCurrentApp(): string
    {
    }

	/**
	 * @param array $entry
	 * @deprecated 20.0.0 Please register your alternative login option using the registerAlternativeLogin() on the RegistrationContext in your Application class implementing the OCP\Authentication\IAlternativeLogin interface
	 */
	public static function registerLogIn(array $entry)
    {
    }

	/**
	 * @return array
	 */
	public static function getAlternativeLogIns(): array
    {
    }

	/**
	 * get a list of all apps in the apps folder
	 *
	 * @return string[] an array of app names (string IDs)
	 * @deprecated 31.0.0 Use IAppManager::getAllAppsInAppsFolders instead
	 */
	public static function getAllApps(): array
    {
    }

	/**
	 * List all supported apps
	 *
	 * @deprecated 32.0.0 Use \OCP\Support\Subscription\IRegistry::delegateGetSupportedApps instead
	 */
	public function getSupportedApps(): array
    {
    }

	/**
	 * List all apps, this is used in apps.php
	 *
	 * @return array
	 */
	public function listAllApps(): array
    {
    }

	/**
	 * @deprecated 32.0.0 Use IAppManager::isUpgradeRequired instead
	 */
	public static function shouldUpgrade(string $app): bool
    {
    }

	/**
	 * Check whether the current Nextcloud version matches the given
	 * application's version requirements.
	 *
	 * The comparison is made based on the number of parts that the
	 * app info version has. For example for ownCloud 6.0.3 if the
	 * app info version is expecting version 6.0, the comparison is
	 * made on the first two parts of the ownCloud version.
	 * This means that it's possible to specify "requiremin" => 6
	 * and "requiremax" => 6 and it will still match ownCloud 6.0.3.
	 *
	 * @param string $ocVersion Nextcloud version to check against
	 * @param array $appInfo app info (from xml)
	 *
	 * @return bool true if compatible, otherwise false
	 * @deprecated 32.0.0 Use IAppManager::isAppCompatible instead
	 */
	public static function isAppCompatible(string $ocVersion, array $appInfo, bool $ignoreMax = false): bool
    {
    }

	/**
	 * get the installed version of all apps
	 * @deprecated 32.0.0 Use IAppManager::getAppInstalledVersions or IAppConfig::getAppInstalledVersions instead
	 */
	public static function getAppVersions(): array
    {
    }

	/**
	 * update the database for the app and call the update script
	 *
	 * @deprecated 32.0.0 Use IAppManager::upgradeApp instead
	 */
	public static function updateApp(string $appId): bool
    {
    }

	/**
	 * @param string $appId
	 * @param string[] $steps
	 * @throws \OC\NeedsUpdateException
	 */
	public static function executeRepairSteps(string $appId, array $steps)
    {
    }

	/**
	 * @deprecated 32.0.0 Use the IJobList directly instead
	 */
	public static function setupBackgroundJobs(array $jobs)
    {
    }

	/**
	 * @param \OCP\IConfig $config
	 * @param \OCP\IL10N $l
	 * @param array $info
	 * @throws \Exception
	 */
	public static function checkAppDependencies(\OCP\IConfig $config, \OCP\IL10N $l, array $info, bool $ignoreMax)
    {
    }
}
