<?php


declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Exceptions;

use Exception;
use JsonSerializable;
use OCP\AppFramework\Http;
use Throwable;

/**
 * Class FederatedItemException
 *
 * @package OCA\Circles\Exceptions
 */
class FederatedItemException extends Exception implements JsonSerializable {
	public static $CHILDREN = [
		FederatedItemBadRequestException::class,
		FederatedItemConflictException::class,
		FederatedItemForbiddenException::class,
		FederatedItemNotFoundException::class,
		FederatedItemRemoteException::class,
		FederatedItemServerException::class,
		FederatedItemUnauthorizedException::class
	];


	/**
	 * FederatedItemException constructor.
	 *
	 * @param string $message
	 * @param int $code
	 * @param Throwable|null $previous
	 */
	public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
 {
 }


	/**
	 * @param int $status
	 */
	protected function setStatus(int $status): void
 {
 }

	/**
	 * @return int
	 */
	public function getStatus(): int
 {
 }


	/**
	 * @return array
	 */
	public function jsonSerialize(): array
 {
 }
}
