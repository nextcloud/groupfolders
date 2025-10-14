<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles;

use OCA\Circles\Db\CoreQueryBuilder;
use OCA\Circles\Db\CoreRequestBuilder;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Exceptions\FederatedUserNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Service\FederatedUserService;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class CirclesQueryHelper
 *
 * @package OCA\Circles
 */
class CirclesQueryHelper {
	/**
	 * CirclesQueryHelper constructor.
	 *
	 * @param CoreRequestBuilder $coreRequestBuilder
	 * @param FederatedUserService $federatedUserService
	 */
	public function __construct(CoreRequestBuilder $coreRequestBuilder, FederatedUserService $federatedUserService)
 {
 }


	/**
	 * @return CoreQueryBuilder&IQueryBuilder
	 */
	public function getQueryBuilder(): CoreQueryBuilder
 {
 }


	/**
	 * @param string $alias
	 * @param string $field
	 * @param bool $fullDetails
	 *
	 * @return ICompositeExpression
	 * @throws RequestBuilderException
	 * @throws FederatedUserNotFoundException
	 */
	public function limitToSession(string $alias, string $field, bool $fullDetails = false): ICompositeExpression
 {
 }


	/**
	 * @param string $alias
	 * @param string $field
	 * @param IFederatedUser $federatedUser
	 * @param bool $fullDetails
	 *
	 * @return ICompositeExpression
	 * @throws RequestBuilderException
	 */
	public function limitToInheritedMembers(string $alias, string $field, IFederatedUser $federatedUser, bool $fullDetails = false): ICompositeExpression
 {
 }

	/**
	 * lighter version with small inner join
	 */
	public function limitToMemberships(string $alias, string $field, IFederatedUser $federatedUser): void
 {
 }


	/**
	 * @param string $field
	 * @param string $alias
	 *
	 * @throws RequestBuilderException
	 */
	public function addCircleDetails(string $alias, string $field): void
 {
 }


	/**
	 * @param array $data
	 *
	 * @return Circle
	 * @throws CircleNotFoundException
	 */
	public function extractCircle(array $data): Circle
 {
 }
}
