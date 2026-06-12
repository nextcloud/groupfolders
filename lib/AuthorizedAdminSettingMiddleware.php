<?php

/**
 * @author Cyrille Bollu <cyr.debian@bollu.be> for Arawa (https://www.arawa.fr/)
 *
 * GroupFolders
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
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
