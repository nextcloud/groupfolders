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
 * Class FederatedItemServerException
 *
 * @package OCA\Circles\Exceptions
 */
class FederatedItemServerException extends FederatedItemException {
	public const STATUS = Http::STATUS_INTERNAL_SERVER_ERROR;


	/**
	 * FederatedItemServerException constructor.
	 *
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
 {
 }
}
