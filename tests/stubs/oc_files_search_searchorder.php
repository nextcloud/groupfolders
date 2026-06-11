<?php

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
	public function getDirection(): string
    {
    }

	/**
	 * @return string
	 */
	public function getField(): string
    {
    }

	/**
	 * @return string
	 * @since 28.0.0
	 */
	public function getExtra(): string
    {
    }

	public function sortFileInfo(FileInfo $a, FileInfo $b): int
    {
    }
}
