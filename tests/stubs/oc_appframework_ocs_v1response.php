<?php
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\AppFramework\OCS;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;

/**
 * @psalm-import-type DataResponseType from DataResponse
 * @template S of int
 * @template-covariant T of DataResponseType
 * @template H of array<string, mixed>
 * @template-extends BaseResponse<int, DataResponseType, array<string, mixed>>
 */
class V1Response extends BaseResponse {
	/**
	 * The V1 endpoint has very limited http status codes basically everything
	 * is status 200 except 401
	 *
	 * @return int
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
