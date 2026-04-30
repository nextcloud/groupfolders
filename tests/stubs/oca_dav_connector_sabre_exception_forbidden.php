<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\Connector\Sabre\Exception;

class Forbidden extends \Sabre\DAV\Exception\Forbidden {
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';

	/**
	 * @param string $message
	 * @param bool $retry
	 * @param \Exception $previous
	 */
	public function __construct($message, private $retry = false, ?\Exception $previous = null)
    {
    }

	/**
	 * This method allows the exception to include additional information
	 * into the WebDAV error response
	 *
	 * @param \Sabre\DAV\Server $server
	 * @param \DOMElement $errorNode
	 * @return void
	 */
	#[\Override]
    public function serialize(\Sabre\DAV\Server $server, \DOMElement $errorNode)
    {
    }
}
