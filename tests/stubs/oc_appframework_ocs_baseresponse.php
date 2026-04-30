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
 * @template-covariant S of Http::STATUS_*
 * @template-covariant T of mixed
 * @template-covariant H of array<string, mixed>
 * @template-extends Response<Http::STATUS_*, array<string, mixed>>
 */
abstract class BaseResponse extends Response {
	/** @var array */
	protected $data;

	/**
	 * BaseResponse constructor.
	 *
	 * @param DataResponse<S, T, H> $dataResponse
	 */
	public function __construct(DataResponse $dataResponse, protected string $format = 'xml', protected ?string $statusMessage = null, protected ?int $itemsCount = null, protected ?int $itemsPerPage = null)
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
