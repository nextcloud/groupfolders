<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles;

use OCA\Circles\Model\Circle;

/**
 * Interface IFederatedUser
 *
 * @package OCA\Circles
 */
interface IFederatedModel {
	public function getInstance(): string {
	}
	public function isLocal(): bool{
	}
}
