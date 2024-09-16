<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Model\Probes;

use OCA\Circles\IQueryProbe;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Federated\RemoteInstance;
use OCA\Circles\Model\Member;

/**
 * Class BasicProbe
 *
 * @package OCA\Circles\Model\Probes
 */
class BasicProbe implements IQueryProbe {
	public const DETAILS_NONE = 0;
	public const DETAILS_POPULATION = 32;
	public const DETAILS_ALL = 127;


	/**
	 * @param int $itemsOffset
	 *
	 * @return BasicProbe
	 */
	public function setItemsOffset(int $itemsOffset): self
 {
 }

	/**
	 * @return int
	 */
	public function getItemsOffset(): int
 {
 }


	/**
	 * @param int $itemsLimit
	 *
	 * @return BasicProbe
	 */
	public function setItemsLimit(int $itemsLimit): self
 {
 }

	/**
	 * @return int
	 */
	public function getItemsLimit(): int
 {
 }


	/**
	 * @param int $details
	 *
	 * @return $this
	 */
	public function setDetails(int $details): self
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
	 * @return $this
	 */
	public function addDetail(int $detail): self
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
	 * @param Circle $filterCircle
	 *
	 * @return CircleProbe
	 */
	public function setFilterCircle(Circle $filterCircle): self
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
	 * @param Member $filterMember
	 *
	 * @return CircleProbe
	 */
	public function setFilterMember(Member $filterMember): self
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
	 * @param RemoteInstance $filterRemoteInstance
	 *
	 * @return CircleProbe
	 */
	public function setFilterRemoteInstance(RemoteInstance $filterRemoteInstance): self
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
	 * @param string $key
	 * @param string $value
	 *
	 * @return $this
	 */
	public function addOption(string $key, string $value): self
 {
 }

	/**
	 * @param string $key
	 * @param int $value
	 *
	 * @return $this
	 */
	public function addOptionInt(string $key, int $value): self
 {
 }

	/**
	 * @param string $key
	 * @param bool $value
	 *
	 * @return $this
	 */
	public function addOptionBool(string $key, bool $value): self
 {
 }

	/**
	 * @return array
	 */
	public function getAsOptions(): array
 {
 }


	/**
	 * @return array
	 */
	public function JsonSerialize(): array
 {
 }
}
