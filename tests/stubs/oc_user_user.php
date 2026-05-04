<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\User;

use InvalidArgumentException;
use OC\Accounts\AccountManager;
use OC\Avatar\AvatarManager;
use OC\Hooks\Emitter;
use OCP\Accounts\IAccountManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Group\Events\BeforeUserRemovedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IImage;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserBackend;
use OCP\Notification\IManager as INotificationManager;
use OCP\Server;
use OCP\User\Backend\IGetHomeBackend;
use OCP\User\Backend\IPasswordHashBackend;
use OCP\User\Backend\IPropertyPermissionBackend;
use OCP\User\Backend\IProvideAvatarBackend;
use OCP\User\Backend\IProvideEnabledStateBackend;
use OCP\User\Backend\ISetDisplayNameBackend;
use OCP\User\Backend\ISetPasswordBackend;
use OCP\User\Events\BeforePasswordUpdatedEvent;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\PasswordUpdatedEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\GetQuotaEvent;
use OCP\UserInterface;
use OCP\Util;
use Psr\Log\LoggerInterface;

use function json_decode;
use function json_encode;

class User implements IUser {
	private const CONFIG_KEY_MANAGERS = 'manager';
	protected ?IAccountManager $accountManager = null;

	public function __construct(private string $uid, private ?UserInterface $backend, private IEventDispatcher $dispatcher, private Emitter|Manager|null $emitter = null, ?IConfig $config = null, $urlGenerator = null)
    {
    }

	#[\Override]
    public function getUID(): string
    {
    }

	/**
	 * Get the display name for the user, if no specific display name is set it will fallback to the user id
	 */
	#[\Override]
    public function getDisplayName(): string
    {
    }

	/**
	 * Set the displayname for the user
	 *
	 * @param string $displayName
	 *
	 * @since 25.0.0 Throw InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	#[\Override]
    public function setDisplayName($displayName): bool
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function setEMailAddress($mailAddress): void
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function setSystemEMailAddress(string $mailAddress): void
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function setPrimaryEMailAddress(string $mailAddress): void
    {
    }

	/**
	 * returns the timestamp of the user's last login or 0 if the user did never
	 * login
	 */
	#[\Override]
    public function getLastLogin(): int
    {
    }

	/**
	 * returns the timestamp of the user's last login or 0 if the user did never
	 * login
	 */
	#[\Override]
    public function getFirstLogin(): int
    {
    }

	/**
	 * updates the timestamp of the most recent login of this user
	 */
	#[\Override]
    public function updateLastLoginTimestamp(): bool
    {
    }

	/**
	 * Delete the user
	 */
	#[\Override]
    public function delete(): bool
    {
    }

	/**
	 * Set the password of the user
	 *
	 * @param string $password
	 * @param string $recoveryPassword for the encryption app to reset encryption keys
	 */
	#[\Override]
    public function setPassword($password, $recoveryPassword = null): bool
    {
    }

	#[\Override]
    public function getPasswordHash(): ?string
    {
    }

	#[\Override]
    public function setPasswordHash(string $passwordHash): bool
    {
    }

	/**
	 * Get the users home folder to mount
	 */
	#[\Override]
    public function getHome(): string
    {
    }

	/**
	 * Get the name of the backend class the user is connected with
	 */
	#[\Override]
    public function getBackendClassName(): string
    {
    }

	#[\Override]
    public function getBackend(): ?UserInterface
    {
    }

	#[\Override]
    public function canChangeAvatar(): bool
    {
    }

	#[\Override]
    public function canChangePassword(): bool
    {
    }

	#[\Override]
    public function canChangeDisplayName(): bool
    {
    }

	#[\Override]
    public function canChangeEmail(): bool
    {
    }

	/**
	 * @param IAccountManager::PROPERTY_*|IAccountManager::COLLECTION_* $property
	 */
	#[\Override]
    public function canEditProperty(string $property): bool
    {
    }

	/**
	 * Check if the user is enabled
	 */
	#[\Override]
    public function isEnabled(): bool
    {
    }

	/**
	 * set the enabled status for the user
	 *
	 * @return void
	 */
	#[\Override]
    public function setEnabled(bool $enabled = true)
    {
    }

	/**
	 * Get the users email address
	 *
	 * @since 9.0.0
	 */
	#[\Override]
    public function getEMailAddress(): ?string
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getSystemEMailAddress(): ?string
    {
    }

	/**
	 * @inheritDoc
	 */
	#[\Override]
    public function getPrimaryEMailAddress(): ?string
    {
    }

	/**
	 * get the users' quota
	 *
	 * @since 9.0.0
	 */
	#[\Override]
    public function getQuota(): string
    {
    }

	#[\Override]
    public function getQuotaBytes(): int|float
    {
    }

	/**
	 * Set the users' quota
	 *
	 * @param string $quota
	 * @throws InvalidArgumentException
	 * @since 9.0.0
	 */
	#[\Override]
    public function setQuota($quota): void
    {
    }

	#[\Override]
    public function getManagerUids(): array
    {
    }

	#[\Override]
    public function setManagerUids(array $uids): void
    {
    }

	/**
	 * get the avatar image if it exists
	 *
	 * @param int $size
	 * @since 9.0.0
	 */
	#[\Override]
    public function getAvatarImage($size): ?IImage
    {
    }

	/**
	 * get the federation cloud id
	 *
	 * @since 9.0.0
	 */
	#[\Override]
    public function getCloudId(): string
    {
    }

	public function triggerChange($feature, $value = null, $oldValue = null): void
    {
    }
}
