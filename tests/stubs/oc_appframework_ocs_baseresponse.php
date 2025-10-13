<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\AppFramework\OCS;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;

/**
 * @psalm-import-type DataResponseType from DataResponse
 * @template S of Http::STATUS_*
 * @template-covariant T of DataResponseType
 * @template H of array<string, mixed>
 * @template-extends Response<Http::STATUS_*, array<string, mixed>>
 */
abstract class BaseResponse extends Response {
	/** @var array */
	protected $data;

	/** @var string */
	protected $format;

	/** @var ?string */
	protected $statusMessage;

	/** @var ?int */
	protected $itemsCount;

	/** @var ?int */
	protected $itemsPerPage;

	/**
	 * BaseResponse constructor.
	 *
	 * @param DataResponse<S, T, H> $dataResponse
	 * @param string $format
	 * @param string|null $statusMessage
	 * @param int|null $itemsCount
	 * @param int|null $itemsPerPage
	 */
	public function __construct(DataResponse $dataResponse, $format = 'xml', $statusMessage = null, $itemsCount = null, $itemsPerPage = null)
 {
 }

	/**
	 * @param array<string,string|int> $meta
	 * @return string
	 */
	protected function renderResult(array $meta): string
 {
 }

	/**
	 * @psalm-taint-escape has_quotes
	 * @psalm-taint-escape html
	 */
	protected function toJson(array $array): string
 {
 }

	protected function toXML(array $array, \XMLWriter $writer): void
 {
 }

	public function getOCSStatus()
 {
 }
}
