<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\DB\QueryBuilder;

use OCP\DB\QueryBuilder\ITypedQueryBuilder;
use RuntimeException;

/**
 * @psalm-suppress InvalidTemplateParam
 * @template-implements ITypedQueryBuilder<string>
 */
abstract class TypedQueryBuilder implements ITypedQueryBuilder {
	#[\Override]
    public function selectColumns(string ...$columns): static
    {
    }

	#[\Override]
    public function selectColumnsDistinct(string ...$columns): static
    {
    }
}
