<?php

namespace OCA\GroupFolders;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class DelegatedAdminsMiddleware extends Middleware {

    /** @var string */
    private $appName;

    /** @var IAppConfig */
    private $appConfig;

    /** @var IGroupManager */
    private $groupManager;

    /** @var IRequest */
    private $request;

    /** @var IUserSession */
    private  $userSession;

    /**
     *
     * @param string $appName
     * @param IAppConfig $appConfig
     * @param IgroupManager $groupManager
     * @param IUserSession $userSession
     *
     */
    public function __construct(string $appName,
        IAppConfig $appConfig,
        IgroupManager $groupManager,
        IRequest $request,
        IUserSession $userSession
        ) {
            $this->appName = $appName;
            $this->appConfig = $appConfig;
            $this->groupManager = $groupManager;
            $this->request = $request;
            $this->userSession = $userSession;
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

        // method 'aclMappingSearch' implements its own access control and method 'isAdmin' must be accesible by everyone
        if ($methodName !== 'aclMappingSearch' && $methodName !== 'isAdmin') {
            // Get allowed groups from app's config
            $appConfigKeys = $this->appConfig->getValues($this->appName, false);
            $delegatedAdmins = str_word_count($appConfigKeys['delegated-admins'], 1, '_-');

            // Find out if user is member of any group(s) granted delegated admin rights
            $userGroups = $this->groupManager->getUserGroups($this->userSession->getUser());
            $result = array_intersect($delegatedAdmins, array_map(function(IGroup $group) {
                return $group->getDisplayName();
            }, $userGroups));

            // Throw an error when user is not member of any such groups
            if (count($result) === 0) {
                \OC::$server->get(LoggerInterface::class)->error('User is not member of a delegated admins group');
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
