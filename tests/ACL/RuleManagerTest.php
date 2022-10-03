<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\groupfolders\tests\ACL;

use OC\Files\Storage\Temporary;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\IUser;
use Test\TestCase;

/**
 * @group DB
 */
class RuleManagerTest extends TestCase {
	/** @var \PHPUnit_Framework_MockObject_MockObject | IUserMappingManager */
	private $userMappingManager;
	/** @var RuleManager */
	private $ruleManager;
	/** @var \PHPUnit_Framework_MockObject_MockObject | IUser */
	private $user;

	/** @var \PHPUnit_Framework_MockObject_MockObject | IEventDispatcher */
	private $eventDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')
			->willReturn('1');

		$this->userMappingManager = $this->createMock(IUserMappingManager::class);
		$this->userMappingManager->expects($this->any())
			->method('mappingFromId')
			->willReturnCallback(function ($type, $id) {
				if ($type === 'user') {
					return new UserMapping($type, $id, 'The User');
				} else {
					return new UserMapping($type, $id);
				}
			});

		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->ruleManager = new RuleManager(\OC::$server->getDatabaseConnection(), $this->userMappingManager, $this->eventDispatcher);
	}

	public function testGetSetRule() {
		$mapping = new UserMapping('user', '1', 'The User');
		$this->userMappingManager->expects($this->any())
			->method('getMappingsForUser')
			->with($this->user)
			->willReturn([$mapping]);

		$this->eventDispatcher->expects($this->any())
			->method('dispatchTyped')
			->withConsecutive(
				[$this->callback(function (CriticalActionPerformedEvent $event): bool {
					return $event->getParameters() === [
						'permissions' => 0b00001001,
						'mask' => 0b00001111,
						'fileId' => 10,
						'user' => 'The User (1)',
					];
				})],
				[$this->callback(function (CriticalActionPerformedEvent $event): bool {
					return $event->getParameters() === [
						'permissions' => 0b00001000,
						'mask' => 0b00001111,
						'fileId' => 10,
						'user' => 'The User (1)',
					];
				})],
				[$this->callback(function (CriticalActionPerformedEvent $event): bool {
					return $event->getParameters() === [
						'fileId' => 10,
						'user' => 'The User (1)',
					];
				})],
			);

		$rule = new Rule($mapping, 10, 0b00001111, 0b00001001);
		$this->ruleManager->saveRule($rule);

		$result = $this->ruleManager->getRulesForFilesById($this->user, [10]);
		$this->assertEquals([10 => [$rule]], $result);

		$updatedRule = new Rule($mapping, 10, 0b00001111, 0b00001000);
		$this->ruleManager->saveRule($updatedRule);

		$result = $this->ruleManager->getRulesForFilesById($this->user, [10]);
		$this->assertEquals([10 => [$updatedRule]], $result);

		// cleanup
		$this->ruleManager->deleteRule($rule);
	}

	public function testGetMultiple() {
		$mapping1 = new UserMapping('test', '1');
		$mapping2 = new UserMapping('test', '2');
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

	public function testGetByPath() {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->mkdir('foo/bar');
		$storage->getScanner()->scan('');
		$cache = $storage->getCache();
		$id1 = (int)$cache->getId('foo');
		$id2 = (int)$cache->getId('foo/bar');
		$storageId = $cache->getNumericStorageId();

		$mapping = new UserMapping('test', '1');
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

	public function testGetByPathMore() {
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
		$id1 = (int)$cache->getId('foo');
		$storageId = $cache->getNumericStorageId();

		$mapping = new UserMapping('test', '1');
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

	public function testGetByParent() {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->mkdir('foo/bar');
		$storage->mkdir('foo/asd');
		$storage->getScanner()->scan('');
		$cache = $storage->getCache();
		$id2 = (int)$cache->getId('foo/bar');
		$id3 = (int)$cache->getId('foo/asd');
		$storageId = $cache->getNumericStorageId();

		$mapping = new UserMapping('test', '1');
		$this->userMappingManager->expects($this->any())
			->method('getMappingsForUser')
			->with($this->user)
			->willReturn([$mapping]);

		$rule1 = new Rule($mapping, $id2, 0b00001111, 0b00001001);
		$rule2 = new Rule($mapping, $id3, 0b00001111, 0b00001000);
		$this->ruleManager->saveRule($rule1);
		$this->ruleManager->saveRule($rule2);

		$result = $this->ruleManager->getRulesForFilesByParent($this->user, $storageId, 'foo');
		$this->assertEquals(['foo/bar' => [$rule1], 'foo/asd' => [$rule2]], $result);

		// cleanup
		$this->ruleManager->deleteRule($rule1);
		$this->ruleManager->deleteRule($rule2);
	}
}
