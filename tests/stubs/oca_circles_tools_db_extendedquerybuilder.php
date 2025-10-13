<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Tools\Db;

use DateInterval;
use DateTime;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;
use Exception;
use OC\DB\QueryBuilder\QueryBuilder;
use OC\SystemConfig;
use OCA\Circles\Tools\Exceptions\DateTimeException;
use OCA\Circles\Tools\Exceptions\InvalidItemException;
use OCA\Circles\Tools\Exceptions\RowNotFoundException;
use OCA\Circles\Tools\Traits\TArrayTools;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Log\LoggerInterface;

class ExtendedQueryBuilder extends QueryBuilder {
	use TArrayTools;


	public function __construct()
 {
 }


	/**
	 * @param string $alias
	 *
	 * @return self
	 */
	public function setDefaultSelectAlias(string $alias): self
 {
 }

	/**
	 * @return string
	 */
	public function getDefaultSelectAlias(): string
 {
 }


	/**
	 * @return array
	 */
	public function getDefaultValues(): array
 {
 }

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return $this
	 */
	public function addDefaultValue(string $key, string $value): self
 {
 }

	/**
	 * @param int $size
	 * @param int $page
	 */
	public function paginate(int $size, int $page = 0): void
 {
 }

	/**
	 * @param int $offset
	 * @param int $limit
	 */
	public function chunk(int $offset, int $limit): void
 {
 }


	/**
	 * Limit the request to the Id
	 *
	 * @param int $id
	 */
	public function limitToId(int $id): void
 {
 }

	/**
	 * @param array $ids
	 */
	public function limitToIds(array $ids): void
 {
 }

	/**
	 * @param string $id
	 */
	public function limitToIdString(string $id): void
 {
 }

	/**
	 * @param string $userId
	 */
	public function limitToUserId(string $userId): void
 {
 }

	/**
	 * @param string $uniqueId
	 */
	public function limitToUniqueId(string $uniqueId): void
 {
 }

	/**
	 * @param string $memberId
	 */
	public function limitToMemberId(string $memberId): void
 {
 }

	/**
	 * @param string $status
	 */
	public function limitToStatus(string $status): void
 {
 }

	/**
	 * @param int $type
	 */
	public function limitToType(int $type): void
 {
 }

	/**
	 * @param string $type
	 */
	public function limitToTypeString(string $type): void
 {
 }

	/**
	 * @param string $token
	 */
	public function limitToToken(string $token): void
 {
 }


	/**
	 * Limit the request to the creation
	 *
	 * @param int $delay
	 *
	 * @return self
	 * @throws Exception
	 */
	public function limitToCreation(int $delay = 0): self
 {
 }


	/**
	 * @param string $field
	 * @param DateTime $date
	 * @param bool $orNull
	 */
	public function limitToDBFieldDateTime(string $field, DateTime $date, bool $orNull = false): void
 {
 }


	/**
	 * @param int $timestamp
	 * @param string $field
	 *
	 * @throws DateTimeException
	 */
	public function limitToSince(int $timestamp, string $field): void
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 */
	public function searchInDBField(string $field, string $value): void
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function like(string $field, string $value, string $alias = '', bool $cs = true): void
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function limit(string $field, string $value, string $alias = '', bool $cs = true): void
 {
 }

	/**
	 * @param string $field
	 * @param int $value
	 * @param string $alias
	 */
	public function limitInt(string $field, int $value, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 */
	public function limitBool(string $field, bool $value, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param bool $orNull
	 * @param string $alias
	 */
	public function limitEmpty(string $field, bool $orNull = false, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param bool $orEmpty
	 * @param string $alias
	 */
	public function limitNull(string $field, bool $orEmpty = false, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function limitArray(string $field, array $value, string $alias = '', bool $cs = true): void
 {
 }

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 * @param int $type
	 */
	public function limitInArray(string $field, array $value, string $alias = '', int $type = IQueryBuilder::PARAM_STR_ARRAY): void
 {
 }

	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 */
	public function limitBitwise(string $field, int $flag, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $gte
	 * @param string $alias
	 */
	public function gt(string $field, int $value, bool $gte = false, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $lte
	 * @param string $alias
	 */
	public function lt(string $field, int $value, bool $lte = false, string $alias = ''): void
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return string
	 */
	public function exprLike(string $field, string $value, string $alias = '', bool $cs = true): string
 {
 }

	public function exprLimit(string $field, string $value, string $alias = '', bool $cs = true): string
 {
 }

	public function exprLimitInt(string $field, int $value, string $alias = ''): string
 {
 }


	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLimitBool(string $field, bool $value, string $alias = ''): string
 {
 }

	/**
	 * @param string $field
	 * @param bool $orNull
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprLimitEmpty(string $field, bool $orNull = false, string $alias = ''): ICompositeExpression
 {
 }

	/**
	 * @param string $field
	 * @param bool $orEmpty
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprLimitNull(string $field, bool $orEmpty = false, string $alias = ''): ICompositeExpression
 {
 }


	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return ICompositeExpression
	 */
	public function exprLimitArray(string $field, array $values, string $alias = '', bool $cs = true): ICompositeExpression
 {
 }

	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 * @param int $type
	 *
	 * @return string
	 */
	public function exprLimitInArray(string $field, array $values, string $alias = '', int $type = IQueryBuilder::PARAM_STR_ARRAY): string
 {
 }


	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLimitBitwise(string $field, int $flag, string $alias = ''): string
 {
 }


	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $lte
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprLt(string $field, int $value, bool $lte = false, string $alias = ''): string
 {
 }

	/**
	 * @param string $field
	 * @param int $value
	 * @param bool $gte
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprGt(string $field, int $value, bool $gte = false, string $alias = ''): string
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function unlike(string $field, string $value, string $alias = '', bool $cs = true): void
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function filter(string $field, string $value, string $alias = '', bool $cs = true): void
 {
 }

	/**
	 * @param string $field
	 * @param int $value
	 * @param string $alias
	 */
	public function filterInt(string $field, int $value, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 */
	public function filterBool(string $field, bool $value, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param bool $norNull
	 * @param string $alias
	 */
	public function filterEmpty(string $field, bool $norNull = false, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param bool $norEmpty
	 * @param string $alias
	 */
	public function filterNull(string $field, bool $norEmpty = false, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 * @param bool $cs
	 */
	public function filterArray(string $field, array $value, string $alias = '', bool $cs = true): void
 {
 }

	/**
	 * @param string $field
	 * @param array $value
	 * @param string $alias
	 */
	public function filterInArray(string $field, array $value, string $alias = ''): void
 {
 }

	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 */
	public function filterBitwise(string $field, int $flag, string $alias = ''): void
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return string
	 */
	public function exprUnlike(string $field, string $value, string $alias = '', bool $cs = true): string
 {
 }


	/**
	 * @param string $field
	 * @param string $value
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return string
	 */
	public function exprFilter(string $field, string $value, string $alias = '', bool $cs = true): string
 {
 }


	/**
	 * @param string $field
	 * @param int $value
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterInt(string $field, int $value, string $alias = ''): string
 {
 }


	/**
	 * @param string $field
	 * @param bool $value
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterBool(string $field, bool $value, string $alias = ''): string
 {
 }

	/**
	 * @param string $field
	 * @param bool $norNull
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprFilterEmpty(string $field, bool $norNull = false, string $alias = ''): ICompositeExpression
 {
 }

	/**
	 * @param string $field
	 * @param bool $norEmpty
	 * @param string $alias
	 *
	 * @return ICompositeExpression
	 */
	public function exprFilterNull(string $field, bool $norEmpty = false, string $alias = ''): ICompositeExpression
 {
 }


	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 * @param bool $cs
	 *
	 * @return ICompositeExpression
	 */
	public function exprFilterArray(string $field, array $values, string $alias = '', bool $cs = true): ICompositeExpression
 {
 }


	/**
	 * @param string $field
	 * @param array $values
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterInArray(string $field, array $values, string $alias = ''): string
 {
 }


	/**
	 * @param string $field
	 * @param int $flag
	 * @param string $alias
	 *
	 * @return string
	 */
	public function exprFilterBitwise(string $field, int $flag, string $alias = ''): string
 {
 }


	/**
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow
	 * @throws RowNotFoundException
	 * @throws InvalidItemException
	 */
	public function asItem(string $object, array $params = []): IQueryRow
 {
 }

	/**
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow[]
	 */
	public function asItems(string $object, array $params = []): array
 {
 }


	/**
	 * @param string $field
	 * @param array $params
	 *
	 * @return IQueryRow
	 * @throws InvalidItemException
	 * @throws RowNotFoundException
	 */
	public function asItemFromField(string $field, array $params = []): IQueryRow
 {
 }

	/**
	 * @param string $field
	 * @param array $params
	 *
	 * @return IQueryRow[]
	 */
	public function asItemsFromField(string $field, array $params = []): array
 {
 }


	/**
	 * @param callable $method
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow
	 * @throws RowNotFoundException
	 */
	public function getRow(callable $method, string $object = '', array $params = []): IQueryRow
 {
 }


	/**
	 * @param callable $method
	 * @param string $object
	 * @param array $params
	 *
	 * @return IQueryRow[]
	 */
	public function getRows(callable $method, string $object = '', array $params = []): array
 {
 }


	/**
	 * @param string $table
	 * @param array $fields
	 * @param string $alias
	 *
	 * @return $this
	 */
	public function generateSelect(string $table, array $fields, string $alias = ''): self
 {
 }


	/**
	 * @param array $fields
	 * @param string $alias
	 * @param string $prefix
	 * @param array<string, mixed> $default
	 *
	 * @return $this
	 */
	public function generateSelectAlias(array $fields, string $alias, string $prefix, array $default = []): self
 {
 }
}
