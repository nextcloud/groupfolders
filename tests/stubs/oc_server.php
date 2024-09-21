<?php
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC;

use bantu\IniGetWrapper\IniGetWrapper;
use OC\Accounts\AccountManager;
use OC\App\AppManager;
use OC\App\AppStore\Bundles\BundleFetcher;
use OC\App\AppStore\Fetcher\AppFetcher;
use OC\AppFramework\Bootstrap\Coordinator;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Http\RequestId;
use OC\AppFramework\Utility\TimeFactory;
use OC\Authentication\Events\LoginFailed;
use OC\Authentication\Listeners\LoginFailedListener;
use OC\Authentication\Listeners\UserLoggedInListener;
use OC\Authentication\LoginCredentials\Store;
use OC\Authentication\Token\IProvider;
use OC\Avatar\AvatarManager;
use OC\Blurhash\Listener\GenerateBlurhashMetadata;
use OC\Collaboration\Collaborators\GroupPlugin;
use OC\Collaboration\Collaborators\MailPlugin;
use OC\Collaboration\Collaborators\RemoteGroupPlugin;
use OC\Collaboration\Collaborators\RemotePlugin;
use OC\Collaboration\Collaborators\UserPlugin;
use OC\Collaboration\Reference\ReferenceManager;
use OC\Command\CronBus;
use OC\Comments\ManagerFactory as CommentsManagerFactory;
use OC\Contacts\ContactsMenu\ActionFactory;
use OC\Contacts\ContactsMenu\ContactsStore;
use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\Diagnostics\EventLogger;
use OC\Diagnostics\QueryLogger;
use OC\Federation\CloudFederationFactory;
use OC\Federation\CloudFederationProviderManager;
use OC\Federation\CloudIdManager;
use OC\Files\Cache\FileAccess;
use OC\Files\Config\MountProviderCollection;
use OC\Files\Config\UserMountCache;
use OC\Files\Config\UserMountCacheListener;
use OC\Files\Lock\LockManager;
use OC\Files\Mount\CacheMountProvider;
use OC\Files\Mount\LocalHomeMountProvider;
use OC\Files\Mount\ObjectHomeMountProvider;
use OC\Files\Mount\ObjectStorePreviewCacheMountProvider;
use OC\Files\Mount\RootMountProvider;
use OC\Files\Node\HookConnector;
use OC\Files\Node\LazyRoot;
use OC\Files\Node\Root;
use OC\Files\SetupManager;
use OC\Files\Storage\StorageFactory;
use OC\Files\Template\TemplateManager;
use OC\Files\Type\Loader;
use OC\Files\View;
use OC\FilesMetadata\FilesMetadataManager;
use OC\FullTextSearch\FullTextSearchManager;
use OC\Http\Client\ClientService;
use OC\Http\Client\NegativeDnsCache;
use OC\IntegrityCheck\Checker;
use OC\IntegrityCheck\Helpers\AppLocator;
use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OC\KnownUser\KnownUserService;
use OC\LDAP\NullLDAPProviderFactory;
use OC\Lock\DBLockingProvider;
use OC\Lock\MemcacheLockingProvider;
use OC\Lock\NoopLockingProvider;
use OC\Lockdown\LockdownManager;
use OC\Log\LogFactory;
use OC\Log\PsrLoggerAdapter;
use OC\Mail\Mailer;
use OC\Memcache\ArrayCache;
use OC\Memcache\Factory;
use OC\Notification\Manager;
use OC\OCM\Model\OCMProvider;
use OC\OCM\OCMDiscoveryService;
use OC\OCS\DiscoveryService;
use OC\Preview\GeneratorHelper;
use OC\Preview\IMagickSupport;
use OC\Preview\MimeIconProvider;
use OC\Profile\ProfileManager;
use OC\Profiler\Profiler;
use OC\Remote\Api\ApiFactory;
use OC\Remote\InstanceFactory;
use OC\RichObjectStrings\Validator;
use OC\Route\CachingRouter;
use OC\Route\Router;
use OC\Security\Bruteforce\Throttler;
use OC\Security\CertificateManager;
use OC\Security\CredentialsManager;
use OC\Security\Crypto;
use OC\Security\CSP\ContentSecurityPolicyManager;
use OC\Security\CSP\ContentSecurityPolicyNonceManager;
use OC\Security\CSRF\CsrfTokenManager;
use OC\Security\CSRF\TokenStorage\SessionStorage;
use OC\Security\Hasher;
use OC\Security\Ip\RemoteAddress;
use OC\Security\RateLimiting\Limiter;
use OC\Security\SecureRandom;
use OC\Security\TrustedDomainHelper;
use OC\Security\VerificationToken\VerificationToken;
use OC\Session\CryptoWrapper;
use OC\Settings\DeclarativeManager;
use OC\SetupCheck\SetupCheckManager;
use OC\Share20\ProviderFactory;
use OC\Share20\ShareHelper;
use OC\SpeechToText\SpeechToTextManager;
use OC\SystemTag\ManagerFactory as SystemTagManagerFactory;
use OC\Talk\Broker;
use OC\Teams\TeamManager;
use OC\Template\JSCombiner;
use OC\Translation\TranslationManager;
use OC\User\AvailabilityCoordinator;
use OC\User\DisplayNameCache;
use OC\User\Listeners\BeforeUserDeletedListener;
use OC\User\Listeners\UserChangedListener;
use OC\User\Session;
use OCA\Files_External\Service\BackendService;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCA\Files_External\Service\UserStoragesService;
use OCA\Theming\ImageManager;
use OCA\Theming\Service\BackgroundService;
use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\Authentication\Token\IProvider as OCPIProvider;
use OCP\BackgroundJob\IJobList;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Command\IBus;
use OCP\Comments\ICommentsManager;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IContactsStore;
use OCP\Defaults;
use OCP\Diagnostics\IEventLogger;
use OCP\Diagnostics\IQueryLogger;
use OCP\Encryption\IFile;
use OCP\Encryption\Keys\IStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Cache\IFileAccess;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Lock\ILockManager;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Storage\IStorageFactory;
use OCP\Files\Template\ITemplateManager;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\GlobalScale\IConfig;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IAvatarManager;
use OCP\IBinaryFinder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\ICertificateManager;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IDBConnection;
use OCP\IEventSourceFactory;
use OCP\IGroupManager;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IPhoneNumberUtil;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IRequestId;
use OCP\IServerContainer;
use OCP\ISession;
use OCP\ITagManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\LDAP\ILDAPProvider;
use OCP\LDAP\ILDAPProviderFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lockdown\ILockdownManager;
use OCP\Log\ILogFactory;
use OCP\Mail\IMailer;
use OCP\OCM\IOCMDiscoveryService;
use OCP\OCM\IOCMProvider;
use OCP\Preview\IMimeIconProvider;
use OCP\Profile\IProfileManager;
use OCP\Profiler\IProfiler;
use OCP\Remote\Api\IApiFactory;
use OCP\Remote\IInstanceFactory;
use OCP\RichObjectStrings\IRichTextFormatter;
use OCP\RichObjectStrings\IValidator;
use OCP\Route\IRouter;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Security\IContentSecurityPolicyManager;
use OCP\Security\ICredentialsManager;
use OCP\Security\ICrypto;
use OCP\Security\IHasher;
use OCP\Security\Ip\IRemoteAddress;
use OCP\Security\ISecureRandom;
use OCP\Security\ITrustedDomainHelper;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\VerificationToken\IVerificationToken;
use OCP\ServerVersion;
use OCP\Settings\IDeclarativeManager;
use OCP\SetupCheck\ISetupCheckManager;
use OCP\Share\IProviderFactory;
use OCP\Share\IShareHelper;
use OCP\SpeechToText\ISpeechToTextManager;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\Talk\IBroker;
use OCP\Teams\ITeamManager;
use OCP\Translation\ITranslationManager;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\BeforeUserLoggedInEvent;
use OCP\User\Events\BeforeUserLoggedInWithCookieEvent;
use OCP\User\Events\BeforeUserLoggedOutEvent;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedInWithCookieEvent;
use OCP\User\Events\UserLoggedOutEvent;
use OCP\User\IAvailabilityCoordinator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Server
 *
 * @package OC
 *
 * TODO: hookup all manager classes
 */
class Server extends ServerContainer implements IServerContainer {
	/**
	 * @param string $webRoot
	 * @param \OC\Config $config
	 */
	public function __construct($webRoot, \OC\Config $config)
 {
 }

	public function boot()
 {
 }

	/**
	 * @return \OCP\Calendar\IManager
	 * @deprecated 20.0.0
	 */
	public function getCalendarManager()
 {
 }

	/**
	 * @return \OCP\Calendar\Resource\IManager
	 * @deprecated 20.0.0
	 */
	public function getCalendarResourceBackendManager()
 {
 }

	/**
	 * @return \OCP\Calendar\Room\IManager
	 * @deprecated 20.0.0
	 */
	public function getCalendarRoomBackendManager()
 {
 }

	/**
	 * @return \OCP\Contacts\IManager
	 * @deprecated 20.0.0
	 */
	public function getContactsManager()
 {
 }

	/**
	 * @return \OC\Encryption\Manager
	 * @deprecated 20.0.0
	 */
	public function getEncryptionManager()
 {
 }

	/**
	 * @return \OC\Encryption\File
	 * @deprecated 20.0.0
	 */
	public function getEncryptionFilesHelper()
 {
 }

	/**
	 * @return \OCP\Encryption\Keys\IStorage
	 * @deprecated 20.0.0
	 */
	public function getEncryptionKeyStorage()
 {
 }

	/**
	 * The current request object holding all information about the request
	 * currently being processed is returned from this method.
	 * In case the current execution was not initiated by a web request null is returned
	 *
	 * @return \OCP\IRequest
	 * @deprecated 20.0.0
	 */
	public function getRequest()
 {
 }

	/**
	 * Returns the preview manager which can create preview images for a given file
	 *
	 * @return IPreview
	 * @deprecated 20.0.0
	 */
	public function getPreviewManager()
 {
 }

	/**
	 * Returns the tag manager which can get and set tags for different object types
	 *
	 * @see \OCP\ITagManager::load()
	 * @return ITagManager
	 * @deprecated 20.0.0
	 */
	public function getTagManager()
 {
 }

	/**
	 * Returns the system-tag manager
	 *
	 * @return ISystemTagManager
	 *
	 * @since 9.0.0
	 * @deprecated 20.0.0
	 */
	public function getSystemTagManager()
 {
 }

	/**
	 * Returns the system-tag object mapper
	 *
	 * @return ISystemTagObjectMapper
	 *
	 * @since 9.0.0
	 * @deprecated 20.0.0
	 */
	public function getSystemTagObjectMapper()
 {
 }

	/**
	 * Returns the avatar manager, used for avatar functionality
	 *
	 * @return IAvatarManager
	 * @deprecated 20.0.0
	 */
	public function getAvatarManager()
 {
 }

	/**
	 * Returns the root folder of ownCloud's data directory
	 *
	 * @return IRootFolder
	 * @deprecated 20.0.0
	 */
	public function getRootFolder()
 {
 }

	/**
	 * Returns the root folder of ownCloud's data directory
	 * This is the lazy variant so this gets only initialized once it
	 * is actually used.
	 *
	 * @return IRootFolder
	 * @deprecated 20.0.0
	 */
	public function getLazyRootFolder()
 {
 }

	/**
	 * Returns a view to ownCloud's files folder
	 *
	 * @param string $userId user ID
	 * @return \OCP\Files\Folder|null
	 * @deprecated 20.0.0
	 */
	public function getUserFolder($userId = null)
 {
 }

	/**
	 * @return \OC\User\Manager
	 * @deprecated 20.0.0
	 */
	public function getUserManager()
 {
 }

	/**
	 * @return \OC\Group\Manager
	 * @deprecated 20.0.0
	 */
	public function getGroupManager()
 {
 }

	/**
	 * @return \OC\User\Session
	 * @deprecated 20.0.0
	 */
	public function getUserSession()
 {
 }

	/**
	 * @return \OCP\ISession
	 * @deprecated 20.0.0
	 */
	public function getSession()
 {
 }

	/**
	 * @param \OCP\ISession $session
	 * @return void
	 */
	public function setSession(\OCP\ISession $session)
 {
 }

	/**
	 * @return \OC\Authentication\TwoFactorAuth\Manager
	 * @deprecated 20.0.0
	 */
	public function getTwoFactorAuthManager()
 {
 }

	/**
	 * @return \OC\NavigationManager
	 * @deprecated 20.0.0
	 */
	public function getNavigationManager()
 {
 }

	/**
	 * @return \OCP\IConfig
	 * @deprecated 20.0.0
	 */
	public function getConfig()
 {
 }

	/**
	 * @return \OC\SystemConfig
	 * @deprecated 20.0.0
	 */
	public function getSystemConfig()
 {
 }

	/**
	 * Returns the app config manager
	 *
	 * @return IAppConfig
	 * @deprecated 20.0.0
	 */
	public function getAppConfig()
 {
 }

	/**
	 * @return IFactory
	 * @deprecated 20.0.0
	 */
	public function getL10NFactory()
 {
 }

	/**
	 * get an L10N instance
	 *
	 * @param string $app appid
	 * @param string $lang
	 * @return IL10N
	 * @deprecated 20.0.0 use DI of {@see IL10N} or {@see IFactory} instead, or {@see \OCP\Util::getL10N()} as a last resort
	 */
	public function getL10N($app, $lang = null)
 {
 }

	/**
	 * @return IURLGenerator
	 * @deprecated 20.0.0
	 */
	public function getURLGenerator()
 {
 }

	/**
	 * @return AppFetcher
	 * @deprecated 20.0.0
	 */
	public function getAppFetcher()
 {
 }

	/**
	 * Returns an ICache instance. Since 8.1.0 it returns a fake cache. Use
	 * getMemCacheFactory() instead.
	 *
	 * @return ICache
	 * @deprecated 8.1.0 use getMemCacheFactory to obtain a proper cache
	 */
	public function getCache()
 {
 }

	/**
	 * Returns an \OCP\CacheFactory instance
	 *
	 * @return \OCP\ICacheFactory
	 * @deprecated 20.0.0
	 */
	public function getMemCacheFactory()
 {
 }

	/**
	 * Returns an \OC\RedisFactory instance
	 *
	 * @return \OC\RedisFactory
	 * @deprecated 20.0.0
	 */
	public function getGetRedisFactory()
 {
 }


	/**
	 * Returns the current session
	 *
	 * @return \OCP\IDBConnection
	 * @deprecated 20.0.0
	 */
	public function getDatabaseConnection()
 {
 }

	/**
	 * Returns the activity manager
	 *
	 * @return \OCP\Activity\IManager
	 * @deprecated 20.0.0
	 */
	public function getActivityManager()
 {
 }

	/**
	 * Returns an job list for controlling background jobs
	 *
	 * @return IJobList
	 * @deprecated 20.0.0
	 */
	public function getJobList()
 {
 }

	/**
	 * @return ILogFactory
	 * @throws \OCP\AppFramework\QueryException
	 * @deprecated 20.0.0
	 */
	public function getLogFactory()
 {
 }

	/**
	 * Returns a router for generating and matching urls
	 *
	 * @return IRouter
	 * @deprecated 20.0.0
	 */
	public function getRouter()
 {
 }

	/**
	 * Returns a SecureRandom instance
	 *
	 * @return \OCP\Security\ISecureRandom
	 * @deprecated 20.0.0
	 */
	public function getSecureRandom()
 {
 }

	/**
	 * Returns a Crypto instance
	 *
	 * @return ICrypto
	 * @deprecated 20.0.0
	 */
	public function getCrypto()
 {
 }

	/**
	 * Returns a Hasher instance
	 *
	 * @return IHasher
	 * @deprecated 20.0.0
	 */
	public function getHasher()
 {
 }

	/**
	 * Returns a CredentialsManager instance
	 *
	 * @return ICredentialsManager
	 * @deprecated 20.0.0
	 */
	public function getCredentialsManager()
 {
 }

	/**
	 * Get the certificate manager
	 *
	 * @return \OCP\ICertificateManager
	 */
	public function getCertificateManager()
 {
 }

	/**
	 * Returns an instance of the HTTP client service
	 *
	 * @return IClientService
	 * @deprecated 20.0.0
	 */
	public function getHTTPClientService()
 {
 }

	/**
	 * Get the active event logger
	 *
	 * The returned logger only logs data when debug mode is enabled
	 *
	 * @return IEventLogger
	 * @deprecated 20.0.0
	 */
	public function getEventLogger()
 {
 }

	/**
	 * Get the active query logger
	 *
	 * The returned logger only logs data when debug mode is enabled
	 *
	 * @return IQueryLogger
	 * @deprecated 20.0.0
	 */
	public function getQueryLogger()
 {
 }

	/**
	 * Get the manager for temporary files and folders
	 *
	 * @return \OCP\ITempManager
	 * @deprecated 20.0.0
	 */
	public function getTempManager()
 {
 }

	/**
	 * Get the app manager
	 *
	 * @return \OCP\App\IAppManager
	 * @deprecated 20.0.0
	 */
	public function getAppManager()
 {
 }

	/**
	 * Creates a new mailer
	 *
	 * @return IMailer
	 * @deprecated 20.0.0
	 */
	public function getMailer()
 {
 }

	/**
	 * Get the webroot
	 *
	 * @return string
	 * @deprecated 20.0.0
	 */
	public function getWebRoot()
 {
 }

	/**
	 * @return \OC\OCSClient
	 * @deprecated 20.0.0
	 */
	public function getOcsClient()
 {
 }

	/**
	 * @return IDateTimeZone
	 * @deprecated 20.0.0
	 */
	public function getDateTimeZone()
 {
 }

	/**
	 * @return IDateTimeFormatter
	 * @deprecated 20.0.0
	 */
	public function getDateTimeFormatter()
 {
 }

	/**
	 * @return IMountProviderCollection
	 * @deprecated 20.0.0
	 */
	public function getMountProviderCollection()
 {
 }

	/**
	 * Get the IniWrapper
	 *
	 * @return IniGetWrapper
	 * @deprecated 20.0.0
	 */
	public function getIniWrapper()
 {
 }

	/**
	 * @return \OCP\Command\IBus
	 * @deprecated 20.0.0
	 */
	public function getCommandBus()
 {
 }

	/**
	 * Get the trusted domain helper
	 *
	 * @return TrustedDomainHelper
	 * @deprecated 20.0.0
	 */
	public function getTrustedDomainHelper()
 {
 }

	/**
	 * Get the locking provider
	 *
	 * @return ILockingProvider
	 * @since 8.1.0
	 * @deprecated 20.0.0
	 */
	public function getLockingProvider()
 {
 }

	/**
	 * @return IMountManager
	 * @deprecated 20.0.0
	 **/
	public function getMountManager()
 {
 }

	/**
	 * @return IUserMountCache
	 * @deprecated 20.0.0
	 */
	public function getUserMountCache()
 {
 }

	/**
	 * Get the MimeTypeDetector
	 *
	 * @return IMimeTypeDetector
	 * @deprecated 20.0.0
	 */
	public function getMimeTypeDetector()
 {
 }

	/**
	 * Get the MimeTypeLoader
	 *
	 * @return IMimeTypeLoader
	 * @deprecated 20.0.0
	 */
	public function getMimeTypeLoader()
 {
 }

	/**
	 * Get the manager of all the capabilities
	 *
	 * @return CapabilitiesManager
	 * @deprecated 20.0.0
	 */
	public function getCapabilitiesManager()
 {
 }

	/**
	 * Get the Notification Manager
	 *
	 * @return \OCP\Notification\IManager
	 * @since 8.2.0
	 * @deprecated 20.0.0
	 */
	public function getNotificationManager()
 {
 }

	/**
	 * @return ICommentsManager
	 * @deprecated 20.0.0
	 */
	public function getCommentsManager()
 {
 }

	/**
	 * @return \OCA\Theming\ThemingDefaults
	 * @deprecated 20.0.0
	 */
	public function getThemingDefaults()
 {
 }

	/**
	 * @return \OC\IntegrityCheck\Checker
	 * @deprecated 20.0.0
	 */
	public function getIntegrityCodeChecker()
 {
 }

	/**
	 * @return \OC\Session\CryptoWrapper
	 * @deprecated 20.0.0
	 */
	public function getSessionCryptoWrapper()
 {
 }

	/**
	 * @return CsrfTokenManager
	 * @deprecated 20.0.0
	 */
	public function getCsrfTokenManager()
 {
 }

	/**
	 * @return IThrottler
	 * @deprecated 20.0.0
	 */
	public function getBruteForceThrottler()
 {
 }

	/**
	 * @return IContentSecurityPolicyManager
	 * @deprecated 20.0.0
	 */
	public function getContentSecurityPolicyManager()
 {
 }

	/**
	 * @return ContentSecurityPolicyNonceManager
	 * @deprecated 20.0.0
	 */
	public function getContentSecurityPolicyNonceManager()
 {
 }

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\BackendService
	 * @deprecated 20.0.0
	 */
	public function getStoragesBackendService()
 {
 }

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\GlobalStoragesService
	 * @deprecated 20.0.0
	 */
	public function getGlobalStoragesService()
 {
 }

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserGlobalStoragesService
	 * @deprecated 20.0.0
	 */
	public function getUserGlobalStoragesService()
 {
 }

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserStoragesService
	 * @deprecated 20.0.0
	 */
	public function getUserStoragesService()
 {
 }

	/**
	 * @return \OCP\Share\IManager
	 * @deprecated 20.0.0
	 */
	public function getShareManager()
 {
 }

	/**
	 * @return \OCP\Collaboration\Collaborators\ISearch
	 * @deprecated 20.0.0
	 */
	public function getCollaboratorSearch()
 {
 }

	/**
	 * @return \OCP\Collaboration\AutoComplete\IManager
	 * @deprecated 20.0.0
	 */
	public function getAutoCompleteManager()
 {
 }

	/**
	 * Returns the LDAP Provider
	 *
	 * @return \OCP\LDAP\ILDAPProvider
	 * @deprecated 20.0.0
	 */
	public function getLDAPProvider()
 {
 }

	/**
	 * @return \OCP\Settings\IManager
	 * @deprecated 20.0.0
	 */
	public function getSettingsManager()
 {
 }

	/**
	 * @return \OCP\Files\IAppData
	 * @deprecated 20.0.0 Use get(\OCP\Files\AppData\IAppDataFactory::class)->get($app) instead
	 */
	public function getAppDataDir($app)
 {
 }

	/**
	 * @return \OCP\Lockdown\ILockdownManager
	 * @deprecated 20.0.0
	 */
	public function getLockdownManager()
 {
 }

	/**
	 * @return \OCP\Federation\ICloudIdManager
	 * @deprecated 20.0.0
	 */
	public function getCloudIdManager()
 {
 }

	/**
	 * @return \OCP\GlobalScale\IConfig
	 * @deprecated 20.0.0
	 */
	public function getGlobalScaleConfig()
 {
 }

	/**
	 * @return \OCP\Federation\ICloudFederationProviderManager
	 * @deprecated 20.0.0
	 */
	public function getCloudFederationProviderManager()
 {
 }

	/**
	 * @return \OCP\Remote\Api\IApiFactory
	 * @deprecated 20.0.0
	 */
	public function getRemoteApiFactory()
 {
 }

	/**
	 * @return \OCP\Federation\ICloudFederationFactory
	 * @deprecated 20.0.0
	 */
	public function getCloudFederationFactory()
 {
 }

	/**
	 * @return \OCP\Remote\IInstanceFactory
	 * @deprecated 20.0.0
	 */
	public function getRemoteInstanceFactory()
 {
 }

	/**
	 * @return IStorageFactory
	 * @deprecated 20.0.0
	 */
	public function getStorageFactory()
 {
 }

	/**
	 * Get the Preview GeneratorHelper
	 *
	 * @return GeneratorHelper
	 * @since 17.0.0
	 * @deprecated 20.0.0
	 */
	public function getGeneratorHelper()
 {
 }
}
