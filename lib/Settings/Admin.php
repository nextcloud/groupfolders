<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Settings;

use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Service\ApplicationService;
use OCA\GroupFolders\Service\DelegationService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\IDelegatedSettings;

class Admin implements IDelegatedSettings {
	public function __construct(
		private IInitialState $initialState,
		private ApplicationService $applicationService,
		private DelegationService $delegationService,
		private IAppManager $appManager,
	) {
	}

	public function getForm(): TemplateResponse {
		\OCP\Util::addScript(Application::APP_ID, 'groupfolders-settings');

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

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 90;
	}

	public function getName(): ?string {
		return null;
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
