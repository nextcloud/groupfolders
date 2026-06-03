<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders;

use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Service\DelegationService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IRequest;
use OCP\IUserSession;

class AuthorizedAdminSettingMiddleware extends Middleware {
	public function __construct(
		private DelegationService $delegatedService,
		private IControllerMethodReflector $reflector,
		private IRequest $request,
		private IUserSession $userSession,
		private FolderManager $folderManager,
	) {
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
		if (!$this->reflector->hasAnnotation('RequireGroupFolderAdmin')) {
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
