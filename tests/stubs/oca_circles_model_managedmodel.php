<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Model;

use OC;
use OCA\Circles\IFederatedUser;

/**
 * Class ManagedModel
 *
 * @package OCA\Circles\Model
 */
class ManagedModel {
	public const ID_LENGTH = 31;


	/**
	 * @return ModelManager
	 */
	protected function getManager(): ModelManager
 {
 }


	/** @noinspection PhpPossiblePolymorphicInvocationInspection */
	public function importFromIFederatedUser(IFederatedUser $orig): void
 {
 }
}
