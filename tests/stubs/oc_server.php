<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC;

use bantu\IniGetWrapper\IniGetWrapper;
use OC\Accounts\AccountManager;
use OC\Activity\EventMerger;
use OC\App\AppManager;
use OC\App\AppStore\Bundles\BundleFetcher;
use OC\AppFramework\Bootstrap\Coordinator;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Http\RequestId;
use OC\AppFramework\Services\AppConfig;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OC\AppFramework\Utility\TimeFactory;
use OC\Authentication\Events\LoginFailed;
use OC\Authentication\Listeners\LoginFailedListener;
use OC\Authentication\Listeners\UserLoggedInListener;
use OC\Authentication\LoginCredentials\Store;
use OC\Authentication\Token\IProvider;
use OC\Authentication\TwoFactorAuth\Registry;
use OC\Avatar\AvatarManager;
use OC\BackgroundJob\JobList;
use OC\Blurhash\Listener\GenerateBlurhashMetadata;
use OC\Collaboration\Collaborators\GroupPlugin;
use OC\Collaboration\Collaborators\MailByMailPlugin;
use OC\Collaboration\Collaborators\RemoteGroupPlugin;
use OC\Collaboration\Collaborators\RemotePlugin;
use OC\Collaboration\Collaborators\Search;
use OC\Collaboration\Collaborators\SearchResult;
use OC\Collaboration\Collaborators\UserByMailPlugin;
use OC\Collaboration\Collaborators\UserPlugin;
use OC\Collaboration\Reference\ReferenceManager;
use OC\Collaboration\Resources\ProviderManager;
use OC\Command\CronBus;
use OC\Comments\ManagerFactory as CommentsManagerFactory;
use OC\Config\UserConfig;
use OC\Contacts\ContactsMenu\ActionFactory;
use OC\Contacts\ContactsMenu\ContactsStore;
use OC\ContextChat\ContentManager;
use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\DB\ConnectionFactory;
use OC\Diagnostics\EventLogger;
use OC\Diagnostics\QueryLogger;
use OC\Encryption\File;
use OC\Encryption\Keys\Storage;
use OC\EventDispatcher\EventDispatcher;
use OC\Federation\CloudFederationFactory;
use OC\Federation\CloudFederationProviderManager;
use OC\Federation\CloudIdManager;
use OC\Files\Cache\FileAccess;
use OC\Files\Config\MountProviderCollection;
use OC\Files\Config\UserMountCache;
use OC\Files\Config\UserMountCacheListener;
use OC\Files\Conversion\ConversionManager;
use OC\Files\FilenameValidator;
use OC\Files\Lock\LockManager;
use OC\Files\Mount\CacheMountProvider;
use OC\Files\Mount\LocalHomeMountProvider;
use OC\Files\Mount\ObjectHomeMountProvider;
use OC\Files\Mount\RootMountProvider;
use OC\Files\Node\HookConnector;
use OC\Files\Node\LazyRoot;
use OC\Files\Node\Root;
use OC\Files\ObjectStore\PrimaryObjectStoreConfig;
use OC\Files\SetupManager;
use OC\Files\Storage\StorageFactory;
use OC\Files\Template\TemplateManager;
use OC\Files\Type\Detection;
use OC\Files\Type\Loader;
use OC\Files\View;
use OC\FilesMetadata\FilesMetadataManager;
use OC\FullTextSearch\FullTextSearchManager;
use OC\Http\Client\ClientService;
use OC\Http\Client\NegativeDnsCache;
use OC\IntegrityCheck\Checker;
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
use OC\Mail\EmailValidator;
use OC\Mail\Mailer;
use OC\Memcache\ArrayCache;
use OC\Memcache\Factory;
use OC\Memcache\NullCache;
use OC\Memcache\Redis;
use OC\Notification\Manager;
use OC\OCM\Model\OCMProvider;
use OC\OCM\OCMDiscoveryService;
use OC\OCS\CoreCapabilities;
use OC\OCS\DiscoveryService;
use OC\Preview\Db\PreviewMapper;
use OC\Preview\GeneratorHelper;
use OC\Preview\IMagickSupport;
use OC\Preview\MimeIconProvider;
use OC\Preview\Watcher;
use OC\Preview\WatcherConnector;
use OC\Profile\ProfileManager;
use OC\Profiler\Profiler;
use OC\Remote\Api\ApiFactory;
use OC\Remote\InstanceFactory;
use OC\RichObjectStrings\RichTextFormatter;
use OC\RichObjectStrings\Validator;
use OC\Route\CachingRouter;
use OC\Route\Router;
use OC\Security\Bruteforce\Capabilities;
use OC\Security\Bruteforce\Throttler;
use OC\Security\CertificateManager;
use OC\Security\CredentialsManager;
use OC\Security\Crypto;
use OC\Security\CSP\ContentSecurityPolicyManager;
use OC\Security\CSRF\CsrfTokenManager;
use OC\Security\CSRF\TokenStorage\SessionStorage;
use OC\Security\Hasher;
use OC\Security\Ip\RemoteAddress;
use OC\Security\RateLimiting\Backend\DatabaseBackend;
use OC\Security\RateLimiting\Backend\IBackend;
use OC\Security\RateLimiting\Backend\MemoryCacheBackend;
use OC\Security\RateLimiting\Limiter;
use OC\Security\RemoteHostValidator;
use OC\Security\SecureRandom;
use OC\Security\Signature\SignatureManager;
use OC\Security\TrustedDomainHelper;
use OC\Security\VerificationToken\VerificationToken;
use OC\Session\CryptoWrapper;
use OC\Session\Memory;
use OC\Settings\DeclarativeManager;
use OC\SetupCheck\SetupCheckManager;
use OC\Share20\ProviderFactory;
use OC\Share20\PublicShareTemplateFactory;
use OC\Share20\ShareHelper;
use OC\Snowflake\APCuSequence;
use OC\Snowflake\FileSequence;
use OC\Snowflake\ISequence;
use OC\Snowflake\SnowflakeDecoder;
use OC\Snowflake\SnowflakeGenerator;
use OC\SpeechToText\SpeechToTextManager;
use OC\Support\Subscription\Assertion;
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
use OC\User\User;
use OCA\Theming\ImageManager;
use OCA\Theming\Service\BackgroundService;
use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\Accounts\IAccountManager;
use OCP\Activity\IEventMerger;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\Authentication\Token\IProvider as OCPIProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\AutoloadNotAllowedException;
use OCP\BackgroundJob\IJobList;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Command\IBus;
use OCP\Comments\ICommentsManager;
use OCP\Comments\ICommentsManagerFactory;
use OCP\Config\IUserConfig;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IContactsStore;
use OCP\ContextChat\IContentManager;
use OCP\Defaults;
use OCP\Diagnostics\IEventLogger;
use OCP\Diagnostics\IQueryLogger;
use OCP\Encryption\IFile;
use OCP\Encryption\Keys\IStorage;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\Cache\IFileAccess;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Conversion\IConversionManager;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\ISetupManager;
use OCP\Files\Lock\ILockManager;
use OCP\Files\Mount\IMountManager;
use OCP\Files\Storage\IStorageFactory;
use OCP\Files\Template\ITemplateManager;
use OCP\FilesMetadata\IFilesMetadataManager;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IAvatarManager;
use OCP\IBinaryFinder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IDBConnection;
use OCP\IEmojiHelper;
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
use OCP\Mail\IEmailValidator;
use OCP\Mail\IMailer;
use OCP\OCM\ICapabilityAwareOCMProvider;
use OCP\OCM\IOCMDiscoveryService;
use OCP\OCM\IOCMProvider;
use OCP\OCS\IDiscoveryService;
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
use OCP\Security\IRemoteHostValidator;
use OCP\Security\ISecureRandom;
use OCP\Security\ITrustedDomainHelper;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\Signature\ISignatureManager;
use OCP\Security\VerificationToken\IVerificationToken;
use OCP\ServerVersion;
use OCP\Settings\IDeclarativeManager;
use OCP\SetupCheck\ISetupCheckManager;
use OCP\Share\IProviderFactory;
use OCP\Share\IPublicShareTemplateFactory;
use OCP\Share\IShareHelper;
use OCP\Snowflake\ISnowflakeDecoder;
use OCP\Snowflake\ISnowflakeGenerator;
use OCP\SpeechToText\ISpeechToTextManager;
use OCP\Support\Subscription\IAssertion;
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
	public function __construct(private string $webRoot, Config $config)
    {
    }

	public function boot()
    {
    }

	/**
	 * Returns a view to ownCloud's files folder
	 *
	 * @param string $userId user ID
	 * @return Folder|null
	 * @deprecated 20.0.0
	 */
	#[\Override]
    public function getUserFolder($userId = null): ?Folder
    {
    }

	public function setSession(ISession $session): void
    {
    }

	/**
	 * Get the webroot
	 *
	 * @return string
	 * @deprecated 20.0.0
	 */
	#[\Override]
    public function getWebRoot(): string
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
	#[\Override]
    public function getL10N($app, $lang = null)
    {
    }
}
