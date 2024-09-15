<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles;

use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\RemoteInstance;
use OCA\Circles\Model\Member;

/**
 * Interface IQueryProbe
 *
 * @package OCA\Circles
 */
interface IQueryProbe {
	/**
	 * @return int
	 */
	public function getItemsOffset(): int
 {
 }

	/**
	 * @return int
	 */
	public function getItemsLimit(): int
 {
 }

	/**
	 * @return int
	 */
	public function getDetails(): int
 {
 }

	/**
	 * @param int $detail
	 *
	 * @return bool
	 */
	public function showDetail(int $detail): bool
 {
 }

	/**
	 * @return Circle
	 */
	public function getFilterCircle(): Circle
 {
 }

	/**
	 * @return bool
	 */
	public function hasFilterCircle(): bool
 {
 }

	/**
	 * @return Member
	 */
	public function getFilterMember(): Member
 {
 }

	/**
	 * @return bool
	 */
	public function hasFilterMember(): bool
 {
 }

	/**
	 * @return RemoteInstance
	 */
	public function getFilterRemoteInstance(): RemoteInstance
 {
 }

	/**
	 * @return bool
	 */
	public function hasFilterRemoteInstance(): bool
 {
 }

	/**
	 * @return array
	 */
	public function getAsOptions(): array
 {
 }
}
