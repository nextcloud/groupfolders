<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2013-2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
use OC\Encryption\HookManager;
use OC\Share20\Hooks;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\Events\UserRemovedEvent;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Server;
use OCP\Share;
use OCP\User\Events\UserChangedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use function OCP\Log\logger;

/**
 * Class that is a namespace for all global OC variables
 * No, we can not put this class in its own file because it is used by
 * OC_autoload!
 */
class OC {
	/**
	 * Associative array for autoloading. classname => filename
	 */
	public static array $CLASSPATH = [];
	/**
	 * The installation path for Nextcloud  on the server (e.g. /srv/http/nextcloud)
	 */
	public static string $SERVERROOT = '';
	/**
	 * the Nextcloud root path for http requests (e.g. /nextcloud)
	 */
	public static string $WEBROOT = '';
	/**
	 * The installation path array of the apps folder on the server (e.g. /srv/http/nextcloud) 'path' and
	 * web path in 'url'
	 */
	public static array $APPSROOTS = [];

	public static string $configDir;

	/**
	 * requested app
	 */
	public static string $REQUESTEDAPP = '';

	/**
	 * check if Nextcloud runs in cli mode
	 */
	public static bool $CLI = false;

	public static \OC\Autoloader $loader;

	public static \Composer\Autoload\ClassLoader $composerAutoloader;

	public static \OC\Server $server;

	/**
	 * @throws \RuntimeException when the 3rdparty directory is missing or
	 *                           the app path list is empty or contains an invalid path
	 */
	public static function initPaths(): void
 {
 }

	public static function checkConfig(): void
 {
 }

	public static function checkInstalled(\OC\SystemConfig $systemConfig): void
 {
 }

	public static function checkMaintenanceMode(\OC\SystemConfig $systemConfig): void
 {
 }

	public static function initSession(): void
 {
 }

	/**
	 * @return bool true if the session expiry should only be done by gc instead of an explicit timeout
	 */
	public static function hasSessionRelaxedExpiry(): bool
 {
 }

	/**
	 * Try to set some values to the required Nextcloud default
	 */
	public static function setRequiredIniValues(): void
 {
 }

	public static function init(): void
 {
 }

	/**
	 * register hooks for the cleanup of cache and bruteforce protection
	 */
	public static function registerCleanupHooks(\OC\SystemConfig $systemConfig): void
 {
 }

	/**
	 * register hooks for sharing
	 */
	public static function registerShareHooks(\OC\SystemConfig $systemConfig): void
 {
 }

	protected static function registerAutoloaderCache(\OC\SystemConfig $systemConfig): void
 {
 }

	/**
	 * Handle the request
	 */
	public static function handleRequest(): void
 {
 }

	/**
	 * Check login: apache auth, auth token, basic auth
	 */
	public static function handleLogin(OCP\IRequest $request): bool
 {
 }

	protected static function handleAuthHeaders(): void
 {
 }

	protected static function tryAppAPILogin(OCP\IRequest $request): bool
 {
 }
}

OC::init();
