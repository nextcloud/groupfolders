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
use OC_Helper;
use OCP\Accounts\IAccountManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
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
use OCP\User\Backend\IGetHomeBackend;
use OCP\User\Backend\IPasswordHashBackend;
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
use Psr\Log\LoggerInterface;

use function json_decode;
use function json_encode;

class User implements IUser {
	private const CONFIG_KEY_MANAGERS = 'manager';

	/** @var IAccountManager */
	protected $accountManager;

	public function __construct(private string $uid, private ?UserInterface $backend, private IEventDispatcher $dispatcher, $emitter = null, ?IConfig $config = null, $urlGenerator = null)
 {
 }

	/**
	 * get the user id
	 *
	 * @return string
	 */
	public function getUID()
 {
 }

	/**
	 * get the display name for the user, if no specific display name is set it will fallback to the user id
	 *
	 * @return string
	 */
	public function getDisplayName()
 {
 }

	/**
	 * set the displayname for the user
	 *
	 * @param string $displayName
	 * @return bool
	 *
	 * @since 25.0.0 Throw InvalidArgumentException
	 * @throws \InvalidArgumentException
	 */
	public function setDisplayName($displayName)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function setEMailAddress($mailAddress)
 {
 }

	/**
	 * @inheritDoc
	 */
	public function setSystemEMailAddress(string $mailAddress): void
 {
 }

	/**
	 * @inheritDoc
	 */
	public function setPrimaryEMailAddress(string $mailAddress): void
 {
 }

	/**
	 * returns the timestamp of the user's last login or 0 if the user did never
	 * login
	 *
	 * @return int
	 */
	public function getLastLogin()
 {
 }

	/**
	 * updates the timestamp of the most recent login of this user
	 */
	public function updateLastLoginTimestamp()
 {
 }

	/**
	 * Delete the user
	 *
	 * @return bool
	 */
	public function delete()
 {
 }

	/**
	 * Set the password of the user
	 *
	 * @param string $password
	 * @param string $recoveryPassword for the encryption app to reset encryption keys
	 * @return bool
	 */
	public function setPassword($password, $recoveryPassword = null)
 {
 }

	public function getPasswordHash(): ?string
 {
 }

	public function setPasswordHash(string $passwordHash): bool
 {
 }

	/**
	 * get the users home folder to mount
	 *
	 * @return string
	 */
	public function getHome()
 {
 }

	/**
	 * Get the name of the backend class the user is connected with
	 *
	 * @return string
	 */
	public function getBackendClassName()
 {
 }

	public function getBackend(): ?UserInterface
 {
 }

	/**
	 * Check if the backend allows the user to change his avatar on Personal page
	 *
	 * @return bool
	 */
	public function canChangeAvatar()
 {
 }

	/**
	 * check if the backend supports changing passwords
	 *
	 * @return bool
	 */
	public function canChangePassword()
 {
 }

	/**
	 * check if the backend supports changing display names
	 *
	 * @return bool
	 */
	public function canChangeDisplayName()
 {
 }

	/**
	 * check if the user is enabled
	 *
	 * @return bool
	 */
	public function isEnabled()
 {
 }

	/**
	 * set the enabled status for the user
	 *
	 * @return void
	 */
	public function setEnabled(bool $enabled = true)
 {
 }

	/**
	 * get the users email address
	 *
	 * @return string|null
	 * @since 9.0.0
	 */
	public function getEMailAddress()
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getSystemEMailAddress(): ?string
 {
 }

	/**
	 * @inheritDoc
	 */
	public function getPrimaryEMailAddress(): ?string
 {
 }

	/**
	 * get the users' quota
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getQuota()
 {
 }

	/**
	 * set the users' quota
	 *
	 * @param string $quota
	 * @return void
	 * @throws InvalidArgumentException
	 * @since 9.0.0
	 */
	public function setQuota($quota)
 {
 }

	public function getManagerUids(): array
 {
 }

	public function setManagerUids(array $uids): void
 {
 }

	/**
	 * get the avatar image if it exists
	 *
	 * @param int $size
	 * @return IImage|null
	 * @since 9.0.0
	 */
	public function getAvatarImage($size)
 {
 }

	/**
	 * get the federation cloud id
	 *
	 * @return string
	 * @since 9.0.0
	 */
	public function getCloudId()
 {
 }

	public function triggerChange($feature, $value = null, $oldValue = null)
 {
 }
}
