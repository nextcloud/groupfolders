<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Circles\Exceptions;

use OCP\AppFramework\Http;
use Throwable;

/**
 * Class FederatedItemRemoteException
 *
 * @package OCA\Circles\Exceptions
 */
class FederatedItemRemoteException extends FederatedItemException {
	public const STATUS = Http::STATUS_REQUEST_TIMEOUT;


	/**
	 * FederatedItemRemoteException constructor.
	 *
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
 {
 }
}
