<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Settings;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Mount\MountPathResolver;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminMountPrefix implements ISettings {
	public function __construct(
		private readonly IInitialState $initialState,
		private readonly MountPathResolver $mountPathResolver,
	) {
	}

	/**
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 */
	#[\Override]
	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('mount_prefix', $this->mountPathResolver->getPrefix());

		Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings-mountprefix');
		Util::addScript(Application::APP_ID, Application::APP_ID . '-settings-mountprefix');

		return new TemplateResponse(Application::APP_ID, 'settings-mountprefix', []);
	}

	#[\Override]
	public function getSection(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getPriority(): int {
		return 80;
	}
}
