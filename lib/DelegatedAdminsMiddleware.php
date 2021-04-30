<?php

/**
 * @author Cyrille Bollu <cyr.debian@bollu.be>
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

use OC\AppFramework\Utility\ControllerMethodReflector;
use OCA\GroupFolders\Service\DelegationService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DelegatedAdminsMiddleware extends Middleware {

    /** @var ControllerMethodReflector */
    private $reflector;

    /** @var DelegationService */
    private $delegationService;

    /** @var LoggerInterface */
    private $logger;

    /** @var IRequest */
    private $request;

    /**
     *
     * @param ControllerMethodReflector $reflector
     * @param DelegationService $delegationService
     * @param IRequest $request
     * @param LoggerInterface $logger
     *
     */
    public function __construct(ControllerMethodReflector $reflector,
                DelegationService $delegationService,
                IRequest $request,
                LoggerInterface $logger) {

                $this->reflector = $reflector;
                $this->delegationService = $delegationService;
                $this->logger = $logger;
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
	if ($this->reflector->hasAnnotation('@RequireGroupFolderAdmin')) {	
            if(!$this->delegationService->isAdmin()) {
    	        $this->logger->error('User is not member of a delegated admins group');
                throw new \Exception('User is not member of a delegated admins group', Http::STATUS_FORBIDDEN);
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
        if (stripos($this->request->getHeader('Accept'),'html') === false) {
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
