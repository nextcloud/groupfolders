<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Storage\Temporary;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
class RuleManagerTest extends TestCase {
	private IUserMappingManager&MockObject $userMappingManager;
	private RuleManager $ruleManager;
	private IUser&MockObject $user;
	private IEventDispatcher&MockObject $eventDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')
			->willReturn('1');

		$this->userMappingManager = $this->createMock(IUserMappingManager::class);
		$this->userMappingManager->expects($this->any())
			->method('mappingFromId')
			->willReturnCallback(fn (string $type, string $id): UserMapping => /** @var 'user'|'group'|'dummy' $type */ new UserMapping($type, $id));

		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->ruleManager = new RuleManager(Server::get(IDBConnection::class), $this->userMappingManager, $this->eventDispatcher);
	}

	public function testGetSetRule(): void {
		$mapping = new UserMapping('user', '1');
		$this->userMappingManager->expects($this->any())
			->method('getMappingsForUser')
			->with($this->user)
			->willReturn([$mapping]);

		$parameters = null;
		$this->eventDispatcher->expects($this->any())
			->method('dispatchTyped')
			->willReturnCallback(function (CriticalActionPerformedEvent $event) use (&$parameters): bool {
				$parameters = $event->getParameters();
				return true;
			});

		$rule = new Rule($mapping, 10, 0b00001111, 0b00001001);
		$this->ruleManager->saveRule($rule);
		$this->assertEquals([
			'permissions' => 0b00001001,
			'mask' => 0b00001111,
			'fileId' => 10,
			'user' => '1 (1)',
		], $parameters);

		$result = $this->ruleManager->getRulesForFilesById($this->user, [10]);
		$this->assertEquals([10 => [$rule]], $result);

		$updatedRule = new Rule($mapping, 10, 0b00001111, 0b00001000);
		$this->ruleManager->saveRule($updatedRule);
		$this->assertEquals([
			'permissions' => 0b00001000,
			'mask' => 0b00001111,
			'fileId' => 10,
			'user' => '1 (1)',
		], $parameters);

		$result = $this->ruleManager->getRulesForFilesById($this->user, [10]);
		$this->assertEquals([10 => [$updatedRule]], $result);

		// cleanup
		$this->ruleManager->deleteRule($rule);
		$this->assertEquals([
			'fileId' => 10,
			'user' => '1 (1)',
		], $parameters);
	}

	public function testGetMultiple(): void {
		$mapping1 = new UserMapping('user', '1');
		$mapping2 = new UserMapping('user', '2');
		$this->userMappingManager->expects($this->any())
			->method('getMappingsForUser')
			->with($this->user)
			->willReturn([$mapping1, $mapping2]);

		$this->eventDispatcher->expects($this->any())
			->method('dispatchTyped');

		$rule1 = new Rule($mapping1, 10, 0b00001111, 0b00001001);
		$rule2 = new Rule($mapping2, 10, 0b00001111, 0b00001000);
		$rule3 = new Rule($mapping2, 11, 0b00001111, 0b00001000);
		$this->ruleManager->saveRule($rule1);
		$this->ruleManager->saveRule($rule2);
		$this->ruleManager->saveRule($rule3);

		$result = $this->ruleManager->getRulesForFilesById($this->user, [10, 11]);
		$this->assertEquals([10 => [$rule1, $rule2], 11 => [$rule3]], $result);

		// cleanup
		$this->ruleManager->deleteRule($rule1);
		$this->ruleManager->deleteRule($rule2);
		$this->ruleManager->deleteRule($rule3);
	}

	public function testGetByPath(): void {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->mkdir('foo/bar');
		$storage->getScanner()->scan('');
		$cache = $storage->getCache();
		$id1 = $cache->getId('foo');
		$id2 = $cache->getId('foo/bar');
		$storageId = $cache->getNumericStorageId();

		$mapping = new UserMapping('user', '1');
		$this->userMappingManager->expects($this->any())
			->method('getMappingsForUser')
			->with($this->user)
			->willReturn([$mapping]);

		$this->eventDispatcher->expects($this->any())
			->method('dispatchTyped');

		$rule1 = new Rule($mapping, $id1, 0b00001111, 0b00001001);
		$rule2 = new Rule($mapping, $id2, 0b00001111, 0b00001000);
		$this->ruleManager->saveRule($rule1);
		$this->ruleManager->saveRule($rule2);

		$result = $this->ruleManager->getRulesForFilesByPath($this->user, $storageId, ['foo', 'foo/bar', 'foo/bar/sub']);
		$this->assertEquals(['foo' => [$rule1], 'foo/bar' => [$rule2], 'foo/bar/sub' => []], $result);

		$result = $this->ruleManager->getAllRulesForPrefix($storageId, 'foo');
		$this->assertEquals(['foo' => [$rule1], 'foo/bar' => [$rule2]], $result);

		// cleanup
		$this->ruleManager->deleteRule($rule1);
		$this->ruleManager->deleteRule($rule2);
	}

	public function testGetByPathMore(): void {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$paths = [];
		for ($i = 0; $i < 1100; $i++) {
			$path = 'foo/' . $i;
			$paths[] = $path;
			$storage->touch($path);
		}

		$storage->getScanner()->scan('');
		$cache = $storage->getCache();
		$id1 = $cache->getId('foo');
		$storageId = $cache->getNumericStorageId();

		$mapping = new UserMapping('user', '1');
		$this->userMappingManager->expects($this->any())
			->method('getMappingsForUser')
			->with($this->user)
			->willReturn([$mapping]);

		$rule = new Rule($mapping, $id1, 0b00001111, 0b00001001);
		$this->ruleManager->saveRule($rule);

		$this->eventDispatcher->expects($this->any())
			->method('dispatchTyped');

		$result = $this->ruleManager->getRulesForFilesByPath($this->user, $storageId, array_merge(['foo'], $paths));

		$expectedResults = [];

		foreach ($paths as $path) {
			$expectedResults[$path] = [];
		}

		$this->assertEquals(array_merge(['foo' => [$rule]], $expectedResults), $result);

		// cleanup
		$this->ruleManager->deleteRule($rule);
	}

	public function testGetByParent(): void {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->mkdir('foo/bar');
		$storage->mkdir('foo/asd');
		$storage->getScanner()->scan('');
		$cache = $storage->getCache();
		$parentId = $cache->getId('foo');
		$id2 = $cache->getId('foo/bar');
		$id3 = $cache->getId('foo/asd');
		$storageId = $cache->getNumericStorageId();

		$mapping = new UserMapping('user', '1');
		$this->userMappingManager->expects($this->any())
			->method('getMappingsForUser')
			->with($this->user)
			->willReturn([$mapping]);

		$rule1 = new Rule($mapping, $id2, 0b00001111, 0b00001001);
		$rule2 = new Rule($mapping, $id3, 0b00001111, 0b00001000);
		$this->ruleManager->saveRule($rule1);
		$this->ruleManager->saveRule($rule2);

		$result = $this->ruleManager->getRulesForFilesByParent($this->user, $storageId, $parentId);
		$this->assertEquals(['foo/bar' => [$rule1], 'foo/asd' => [$rule2]], $result);

		// cleanup
		$this->ruleManager->deleteRule($rule1);
		$this->ruleManager->deleteRule($rule2);
	}
}
