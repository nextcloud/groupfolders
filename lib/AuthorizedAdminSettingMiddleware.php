<?php

declare (strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders;

use Exception;
use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use OCA\GroupFolders\Attribute\RequireGroupFolderAdmin;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Service\DelegationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\IUserSession;
use ReflectionMethod;

class AuthorizedAdminSettingMiddleware extends Middleware {
	public function __construct(
		private readonly DelegationService $delegatedService,
		private readonly IRequest $request,
		private readonly IUserSession $userSession,
		private readonly FolderManager $folderManager,
	) {
	}

	/**
	 * Throws an error when the user is not allowed to use the app's APIs
	 */
	#[\Override]
	public function beforeController(Controller $controller, string $methodName): void {
		$method = new ReflectionMethod($controller, $methodName);
		if ($method->getAttributes(RequireGroupFolderAdmin::class) === []) {
			return;
		}

		if ($this->delegatedService->isAdminNextcloud() || $this->delegatedService->isDelegatedAdmin()) {
			return;
		}

		if ($this->delegatedService->hasOnlyApiAccess()
			&& ($user = $this->userSession->getUser()) !== null
			&& ($id = $this->request->getParam('id')) !== null) {
			/** @var string $id */
			if ($this->folderManager->canManageACL((int)$id, $user)) {
				return;
			}
		}

		throw new NotAdminException('Logged in user must be an admin, a sub admin or gotten special right to access this setting');
	}

	#[\Override]
	public function afterException(Controller $controller, string $methodName, Exception $exception): Response {
		/** @var Http::STATUS_* $code */
		$code = $exception->getCode();

		if (stripos($this->request->getHeader('Accept'), 'html') === false) {
			return new JSONResponse(
				['message' => $exception->getMessage()],
				$code
			);
		}

		return new TemplateResponse('core', '403', ['message' => $exception->getMessage()], 'guest', $code);
	}
}
