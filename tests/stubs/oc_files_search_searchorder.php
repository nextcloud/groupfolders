<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\Files\Search;

use OCP\Files\FileInfo;
use OCP\Files\Search\ISearchOrder;

class SearchOrder implements ISearchOrder {
	public function __construct(
		private string $direction,
		private string $field,
		private string $extra = '',
	) {
	}

	/**
	 * @return string
	 */
	#[\Override]
    public function getDirection(): string
    {
    }

	/**
	 * @return string
	 */
	#[\Override]
    public function getField(): string
    {
    }

	/**
	 * @return string
	 * @since 28.0.0
	 */
	#[\Override]
    public function getExtra(): string
    {
    }

	#[\Override]
    public function sortFileInfo(FileInfo $a, FileInfo $b): int
    {
    }
}
