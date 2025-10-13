<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles;

/**
 * Interface IFederatedModel
 *
 * @package OCA\Circles
 */
interface IFederatedModel {
	/**
	 * @return string
	 */
	public function getInstance(): string
 {
 }

	/**
	 * @return bool
	 */
	public function isLocal(): bool
 {
 }
}
