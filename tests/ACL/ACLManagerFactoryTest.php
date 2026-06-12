<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\IAppConfig;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ACLManagerFactoryTest extends TestCase {
	private RuleManager&MockObject $ruleManager;
	private IAppConfig&MockObject $config;
	private IUserMappingManager&MockObject $userMappingManager;
	private ACLManagerFactory $factory;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();

		$this->ruleManager = $this->createMock(RuleManager::class);
		$this->config = $this->createMock(IAppConfig::class);
		$this->userMappingManager = $this->createMock(IUserMappingManager::class);
		$this->factory = new ACLManagerFactory($this->ruleManager, $this->config, $this->userMappingManager);
	}

	private function createUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		return $user;
	}

	public function testReturnsSameInstanceForSameUser(): void {
		$user = $this->createUser('alice');

		$this->assertSame(
			$this->factory->getACLManager($user),
			$this->factory->getACLManager($user),
			'the same user must share one ACLManager (and one rule cache) per request',
		);
	}

	public function testReturnsSameInstanceForDifferentUserObjectsWithSameUid(): void {
		$this->assertSame(
			$this->factory->getACLManager($this->createUser('alice')),
			$this->factory->getACLManager($this->createUser('alice')),
			'the manager is keyed by UID, not by the user object identity',
		);
	}

	public function testReturnsDifferentInstancesForDifferentUsers(): void {
		$this->assertNotSame(
			$this->factory->getACLManager($this->createUser('alice')),
			$this->factory->getACLManager($this->createUser('bob')),
		);
	}
}
