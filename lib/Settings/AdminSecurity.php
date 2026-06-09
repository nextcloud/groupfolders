<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Settings;

use OCA\GroupFolders\AppInfo\Application;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSecurity implements ISettings {
	public function __construct(
		private readonly IInitialState $initialState,
		private readonly IAppConfig $appConfig,
	) {
	}

	/**
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 */
	#[\Override]
	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState(
			'server_side_encryption',
			$this->appConfig->getValueString('core', 'encryption_enabled', 'no'),
		);
		$this->initialState->provideInitialState(
			'enable_encryption',
			$this->appConfig->getValueBool(Application::APP_ID, 'enable_encryption', false),
		);

		Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings-security');
		Util::addScript(Application::APP_ID, Application::APP_ID . '-settings-security');

		return new TemplateResponse(Application::APP_ID, 'settings-security', []);
	}

	#[\Override]
	public function getSection(): string {
		return 'security';
	}

	#[\Override]
	public function getPriority(): int {
		return 12;
	}
}
