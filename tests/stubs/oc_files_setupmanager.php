<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\Files;

use OC\Files\Config\MountProviderCollection;
use OC\Files\Mount\HomeMountPoint;
use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Common;
use OC\Files\Storage\Home;
use OC\Files\Storage\Storage;
use OC\Files\Storage\Wrapper\Availability;
use OC\Files\Storage\Wrapper\Encoding;
use OC\Files\Storage\Wrapper\PermissionsMask;
use OC\Files\Storage\Wrapper\Quota;
use OC\Lockdown\Filesystem\NullStorage;
use OC\Share\Share;
use OC\Share20\ShareDisableChecker;
use OC_App;
use OC_Hook;
use OC_Util;
use OCA\Files_External\Config\ConfigAdapter;
use OCA\Files_Sharing\External\Mount;
use OCA\Files_Sharing\ISharedMountPoint;
use OCA\Files_Sharing\SharedMount;
use OCP\Constants;
use OCP\Diagnostics\IEventLogger;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IHomeMountProvider;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Events\BeforeFileSystemSetupEvent;
use OCP\Files\Events\InvalidateMountCacheEvent;
use OCP\Files\Events\Node\FilesystemTornDownEvent;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lockdown\ILockdownManager;
use OCP\Share\Events\ShareCreatedEvent;
use Psr\Log\LoggerInterface;

class SetupManager {
	public function __construct(private IEventLogger $eventLogger, private MountProviderCollection $mountProviderCollection, private IMountManager $mountManager, private IUserManager $userManager, private IEventDispatcher $eventDispatcher, private IUserMountCache $userMountCache, private ILockdownManager $lockdownManager, private IUserSession $userSession, ICacheFactory $cacheFactory, private LoggerInterface $logger, private IConfig $config, private ShareDisableChecker $shareDisableChecker)
 {
 }

	public function isSetupComplete(IUser $user): bool
 {
 }

	/**
	 * Setup the full filesystem for the specified user
	 */
	public function setupForUser(IUser $user): void
 {
 }

	/**
	 * Set up the root filesystem
	 */
	public function setupRoot(): void
 {
 }

	/**
	 * Set up the filesystem for the specified path
	 */
	public function setupForPath(string $path, bool $includeChildren = false): void
 {
 }

	/**
	 * @param string $path
	 * @param string[] $providers
	 */
	public function setupForProvider(string $path, array $providers): void
 {
 }

	public function tearDown()
 {
 }
}
