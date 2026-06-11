<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\Files\Search;

use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchOperator;

class SearchBinaryOperator implements ISearchBinaryOperator {
	/**
	 * SearchBinaryOperator constructor.
	 *
	 * @param string $type
	 * @param ISearchOperator[] $arguments
	 */
	public function __construct(
		private $type,
		private array $arguments,
	) {
	}

	/**
	 * @return string
	 */
	#[\Override]
    public function getType()
    {
    }

	/**
	 * @return ISearchOperator[]
	 */
	#[\Override]
    public function getArguments()
    {
    }

	/**
	 * @param ISearchOperator[] $arguments
	 * @return void
	 */
	public function setArguments(array $arguments): void
    {
    }

	#[\Override]
    public function getQueryHint(string $name, $default)
    {
    }

	#[\Override]
    public function setQueryHint(string $name, $value): void
    {
    }

	public function __toString(): string
    {
    }
}
