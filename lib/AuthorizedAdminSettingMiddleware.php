<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders;

use Exception;
use OCA\GroupFolders\Service\DelegationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IRequest;

class AuthorizedAdminSettingMiddleware extends Middleware {
	public function __construct(
		private DelegationService $delegatedService,
		private IControllerMethodReflector $reflector,
		private IRequest $request,
	) {
	}

	/**
	 * Throws an error when the user is not allowed to use the app's APIs
	 */
	public function beforeController(Controller $controller, string $methodName): void {
		if ($this->reflector->hasAnnotation('RequireGroupFolderAdmin')) {
			if (!$this->delegatedService->hasApiAccess()) {
				throw new Exception('Logged in user must be an admin, a sub admin or gotten special right to access this setting');
			}
		}
	}

	public function afterException(Controller $controller, string $methodName, Exception $exception): Response {
		if (stripos($this->request->getHeader('Accept'), 'html') === false) {
			$response = new JSONResponse(
				['message' => $exception->getMessage()],
				(int)$exception->getCode()
			);
		} else {
			$response = new TemplateResponse('core', '403', ['message' => $exception->getMessage()], 'guest');
			$response->setStatus((int)$exception->getCode());
		}

		return $response;
	}
}
