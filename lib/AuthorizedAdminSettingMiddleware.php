<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders;

use Exception;
use OCA\GroupFolders\Service\DelegationService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IRequest;

class AuthorizedAdminSettingMiddleware extends Middleware {
	private DelegationService $delegatedService;
	private IControllerMethodReflector $reflector;
	private IRequest $request;

	public function __construct(
		DelegationService $delegatedService,
		IControllerMethodReflector $reflector,
		IRequest $request
	) {
		$this->delegatedService = $delegatedService;
		$this->reflector = $reflector;
		$this->request = $request;
	}

	/**
	 *
	 * {@inheritDoc}
	 * @see \OCP\AppFramework\Middleware::beforeController()
	 *
	 * Throws an error when the user is not allowed to use the app's APIs
	 *
	 */
	public function beforeController($controller, $methodName) {
		if ($this->reflector->hasAnnotation('RequireGroupFolderAdmin')) {
			if (!$this->delegatedService->hasApiAccess()) {
				throw new Exception('Logged in user must be an admin, a sub admin or gotten special right to access this setting');
			}
		}
	}

	/**
	 *
	 * {@inheritDoc}
	 * @see \OCP\AppFramework\Middleware::afterException()
	 *
	 */
	public function afterException($controller, $methodName, \Exception $exception): Response {
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
