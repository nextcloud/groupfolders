<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Richdocuments\Db;

use OCA\Richdocuments\AppConfig;
use OCA\Richdocuments\Exceptions\ExpiredTokenException;
use OCA\Richdocuments\Exceptions\UnknownTokenException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<Wopi> */
class WopiMapper extends QBMapper {
	public function __construct(IDBConnection $db, private ISecureRandom $random, private LoggerInterface $logger, private ITimeFactory $timeFactory, private AppConfig $appConfig)
    {
    }

	/**
	 * @param int $fileId
	 * @param string $owner
	 * @param string $editor
	 * @param string $version
	 * @param bool $updatable
	 * @param string $serverHost
	 * @param string $guestDisplayname
	 * @param int $templateDestination
	 * @return Wopi
	 */
	public function generateFileToken($fileId, $owner, $editor, $version, $updatable, $serverHost, ?string $guestDisplayname = null, $hideDownload = false, $direct = false, $templateId = 0, $share = null)
    {
    }

	public function generateUserSettingsToken($fileId, $userId, $version, $serverHost)
    {
    }

	public function generateInitiatorToken($uid, $remoteServer)
    {
    }

	/**
	 *
	 * @deprecated
	 * @param $token
	 * @return Wopi
	 * @throws ExpiredTokenException
	 * @throws UnknownTokenException
	 */
	public function getPathForToken(
        #[\SensitiveParameter]
		$token
    ): Wopi
    {
    }

	/**
	 * Given a token, validates it and
	 * constructs and validates the path.
	 * Returns the path, if valid, else false.
	 *
	 * @param string $token
	 * @return Wopi
	 * @throws UnknownTokenException
	 * @throws ExpiredTokenException
	 */
	public function getWopiForToken(
        #[\SensitiveParameter]
		string $token
    ): Wopi
    {
    }

	/**
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return int[]
	 * @throws \OCP\DB\Exception
	 */
	public function getExpiredTokenIds(?int $limit = null, ?int $offset = null): array
    {
    }
}
