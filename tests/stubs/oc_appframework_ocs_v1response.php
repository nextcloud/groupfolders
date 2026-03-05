<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\AppFramework\OCS;

use OCP\AppFramework\Http;
use OCP\AppFramework\OCSController;

/**
 * @template-covariant S of Http::STATUS_*
 * @template-covariant H of array<string, mixed>
 * @template-extends BaseResponse<Http::STATUS_*, mixed, array<string, mixed>>
 */
class V1Response extends BaseResponse {
	/**
	 * The V1 endpoint has very limited http status codes basically everything
	 * is status 200 except 401
	 *
	 * @return Http::STATUS_*
	 */
	public function getStatus()
 {
 }

	/**
	 * In v1 all OK is 100
	 *
	 * @return int
	 */
	public function getOCSStatus()
 {
 }

	/**
	 * Construct the meta part of the response
	 * And then late the base class render
	 *
	 * @return string
	 */
	public function render()
 {
 }
}
