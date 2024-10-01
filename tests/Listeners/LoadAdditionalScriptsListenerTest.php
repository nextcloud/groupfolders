<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Tests\Listeners;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\GroupFolders\Listeners\LoadAdditionalScriptsListener;
use OCP\EventDispatcher\Event;
use OCP\Util;
use Test\TestCase;

class LoadAdditionalScriptsListenerTest extends TestCase {
	private LoadAdditionalScriptsListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->listener = new LoadAdditionalScriptsListener();
	}

	public static function handleProvider(): array {
		$expectedScripts = [
			'groupfolders/l10n/en',
			'groupfolders/js/groupfolders-init',
			'groupfolders/js/groupfolders-files',
		];
		return [
			[Event::class, []],
			[LoadAdditionalScriptsEvent::class, $expectedScripts],
			[BeforeTemplateRenderedEvent::class, $expectedScripts],
		];
	}

	/**
	 * @dataProvider handleProvider
	 * @param class-string<LoadAdditionalScriptsEvent|BeforeTemplateRenderedEvent> $class
	 */
	public function testHandle(string $class, array $expectedScripts): void {
		$event = $this->createMock($class);

		$this->listener->handle($event);
		$this->assertEquals($expectedScripts, array_values(Util::getScripts()));
	}
}
