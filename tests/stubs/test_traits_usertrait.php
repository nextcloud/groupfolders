<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test\Traits;

use OC\User\User;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use OCP\UserInterface;

class DummyUser extends User {
	public function __construct(
		private string $uid,
	) {
		parent::__construct($this->uid, null, Server::get(IEventDispatcher::class));
	}

	public function getUID(): string {
		return $this->uid;
	}
}

/**
 * Allow creating users in a temporary backend
 */
trait UserTrait {
	/**
	 * @var \Test\Util\User\Dummy|UserInterface
	 */
	protected $userBackend;

	protected function createUser($name, $password): IUser
 {
 }

	protected function setUpUserTrait()
 {
 }

	protected function tearDownUserTrait()
 {
 }
}
