<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\AppInfo;

use OCA\GroupFolders\AppInfo\Capabilities;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\App\IAppManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CapabilitiesTest extends TestCase {
	private IUserSession&MockObject $userSession;
	private FolderManager&MockObject $folderManager;
	private IAppManager&MockObject $appManager;
	private Capabilities $capabilities;

	public function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->folderManager = $this->createMock(FolderManager::class);
		$this->appManager = $this->createMock(IAppManager::class);

		$this->capabilities = new Capabilities(
			$this->userSession,
			$this->folderManager,
			$this->appManager,
		);
	}

	public function testGetCapabilitiesWithoutUser(): void {
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->assertEquals([], $this->capabilities->getCapabilities());
	}

	public function testGetCapabilitiesWithGroupfolders(): void {
		$user = $this->createMock(IUser::class);

		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->appManager
			->expects($this->once())
			->method('getAppVersion')
			->with('groupfolders')
			->willReturn('1.2.3');

		$this->folderManager
			->expects($this->once())
			->method('getFoldersForUser')
			->with($user)
			->willReturn(['key' => 'value']);

		$this->assertEquals([
			'groupfolders' => [
				'appVersion' => '1.2.3',
				'hasGroupFolders' => true,
			]
		], $this->capabilities->getCapabilities());
	}


	public function testGetCapabilitiesWithoutGroupfolders(): void {
		$user = $this->createMock(IUser::class);

		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->appManager
			->expects($this->once())
			->method('getAppVersion')
			->with('groupfolders')
			->willReturn('1.2.3');

		$this->folderManager
			->expects($this->once())
			->method('getFoldersForUser')
			->with($user)
			->willReturn([]);

		$this->assertEquals([
			'groupfolders' => [
				'appVersion' => '1.2.3',
				'hasGroupFolders' => false,
			]
		], $this->capabilities->getCapabilities());
	}
}
