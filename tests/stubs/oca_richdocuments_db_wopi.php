<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Richdocuments\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @package OCA\Richdocuments\Db
 *
 * @method void setOwnerUid(string $uid)
 * @method string getOwnerUid()
 * @method void setEditorUid(?string $uid)
 * @method string getEditorUid()
 * @method void setFileid(int $fileid)
 * @method int getFileid()
 * @method void setVersion(string $version)
 * @method string getVersion()
 * @method void setCanwrite(bool $canwrite)
 * @method bool getCanwrite()
 * @method void setServerHost(string $host)
 * @method string getServerHost()
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setTokenType(int $tokenType)
 * @method int getTokenType()
 * @method void setRemoteServer(string $remoteServer)
 * @method string getRemoteServer()
 * @method void setRemoteServerToken(string $remoteToken)
 * @method string getRemoteServerToken()
 * @method void setExpiry(int $expiry)
 * @method int getExpiry()
 * @method void setGuestDisplayname(string $guestDisplayName)
 * @method string getGuestDisplayname()
 * @method void setTemplateDestination(int $fileId)
 * @method int getTemplateDestination()
 * @method void setTemplateId(int $fileId)
 * @method int getTemplateId()
 * @method void setShare(string $token)
 * @method string getShare()
 */
class Wopi extends Entity implements \JsonSerializable {
	/**
	 * WOPI token to open a file as a user on the current instance
	 */
	public const TOKEN_TYPE_USER = 0;

	/**
	 * WOPI token to open a file as a guest on the current instance
	 */
	public const TOKEN_TYPE_GUEST = 1;

	/**
	 * WOPI token to open a file as a user from a federated instance
	 */
	public const TOKEN_TYPE_REMOTE_USER = 2;

	/**
	 * WOPI token to open a file as a guest from a federated instance
	 */
	public const TOKEN_TYPE_REMOTE_GUEST = 3;

	/*
	 * Temporary token that is used to share the initiator details to the source instance
	 */
	public const TOKEN_TYPE_INITIATOR = 4;

	/*
	 * Temporary token that is used for authentication while communication between cool iframe and user/admin settings
	 */
	public const TOKEN_TYPE_SETTING_AUTH = 5;

	/** @var string */
	protected $ownerUid;

	/** @var string */
	protected $editorUid;

	/** @var int */
	protected $fileid;

	/** @var string */
	protected $version;

	/** @var bool */
	protected $canwrite;

	/** @var string */
	protected $serverHost;

	/** @var string */
	protected $token;

	/** @var int */
	protected $expiry;

	/** @var string */
	protected $guestDisplayname;

	/** @var int */
	protected $templateDestination;

	/** @var int */
	protected $templateId;

	/** @var bool */
	protected $hideDownload;

	/** @var bool */
	protected $direct;

	/** @var string */
	protected $remoteServer;

	/** @var string */
	protected $remoteServerToken;

	/** @var string */
	protected $share;

	/** @var int */
	protected $tokenType = 0;

	public function __construct()
 {
 }

	public function hasTemplateId()
 {
 }

	public function isGuest()
 {
 }

	public function isRemoteToken()
 {
 }

	public function getUserForFileAccess()
 {
 }

	public function getHideDownload()
 {
 }

	public function getDirect()
 {
 }

	#[\Override]
 #[\ReturnTypeWillChange]
 public function jsonSerialize()
 {
 }
}
