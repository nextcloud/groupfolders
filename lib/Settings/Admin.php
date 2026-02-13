<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Settings;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Service\ApplicationService;
use OCA\GroupFolders\Service\DelegationService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\IDelegatedSettings;
use OCP\Util;

class Admin implements IDelegatedSettings {
	public function __construct(
		private readonly IInitialState $initialState,
		private readonly ApplicationService $applicationService,
		private readonly DelegationService $delegationService,
		private readonly IAppManager $appManager,
	) {
	}

	/**
	 * @return TemplateResponse<Http::STATUS_OK, array{}>
	 */
	#[\Override]
	public function getForm(): TemplateResponse {
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings');
		Util::addScript(Application::APP_ID, Application::APP_ID . '-settings');

		$this->initialState->provideInitialState(
			'checkAppsInstalled',
			$this->applicationService->checkAppsInstalled()
		);

		$this->initialState->provideInitialState(
			'isAdminNextcloud',
			$this->delegationService->isAdminNextcloud()
		);

		$this->initialState->provideInitialState(
			'isCirclesEnabled',
			$this->appManager->isEnabledForUser('circles')
		);

		return new TemplateResponse(
			Application::APP_ID,
			'index',
			['appId' => Application::APP_ID],
			''
		);
	}

	#[\Override]
	public function getSection(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getPriority(): int {
		return 90;
	}

	#[\Override]
	public function getName(): ?string {
		return null;
	}

	/**
	 * @return array<never>
	 */
	#[\Override]
	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
