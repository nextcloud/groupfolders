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

use Exception;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\AppFramework\Http;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\Response;
use OC\Settings\AuthorizedGroupMapper;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCA\GroupFolders\Service\DelegationService;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Utility\IControllerMethodReflector;

class AuthorizedAdminSettingMiddleware extends Middleware {

	/** @var IControllerMethodReflector */
	private $reflector;

	/** @var ControllerMethodReflector */
	private $reflectorPrivate;

	/** @var AuthorizedGroupMapper */
	private $groupAuthorizationMapper;

	/** @var IUserSession */
	private $userSession;

	/** @var IGroupManager */
	private $groupManager;

	/** @var DelegationService */
	private $delegationService;

	/** @var LoggerInterface */
	private $logger;

	/** @var IRequest */
	private $request;

	/**
	 *
	 * @param IControllerMethodReflector $reflector
	 * @param DelegationService $delegationService
	 * @param IRequest $request
	 * @param LoggerInterface $logger
	 *
	 */
	public function __construct(IControllerMethodReflector $reflector,
				DelegationService $delegationService,
				IRequest $request,
				LoggerInterface $logger,
				ControllerMethodReflector $reflectorPrivate,
				AuthorizedGroupMapper $groupAuthorizationMapper,
				IUserSession $userSession,
				IGroupManager $groupManager) {

				$this->reflector = $reflector;
				$this->delegationService = $delegationService;
				$this->logger = $logger;
				$this->request = $request;
				$this->reflectorPrivate = $reflectorPrivate;
				$this->groupAuthorizationMapper = $groupAuthorizationMapper;
				$this->userSession = $userSession;
				$this->groupManager = $groupManager;
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
		if ($this->reflector->hasAnnotation('AuthorizedAdminSetting')) {

			$settingClasses = explode(';', $this->reflectorPrivate->getAnnotationParameter('AuthorizedAdminSetting', 'settings'));
			$authorizedClasses = $this->groupAuthorizationMapper->findAllClassesForUser($this->userSession->getUser());
			foreach ($settingClasses as $settingClass) {
				$authorized = in_array($settingClass, $authorizedClasses, true);
				if ($authorized) {
					break;
				}
			}

			if (!$authorized) {
				if ($this->reflector->hasAnnotation('RequireGroupFolderAdmin')) {	
					if (!$this->delegationService->isSubAdmin()) {
						$this->logger->error('User is not member of a delegated admins group');
						throw new \Exception('User is not member of a delegated admins group', Http::STATUS_FORBIDDEN);
					}
				}
			}

			if (!$authorized) {
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
