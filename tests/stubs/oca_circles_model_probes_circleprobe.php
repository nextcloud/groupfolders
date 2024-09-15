<?php

declare(strict_types=1);


/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace OCA\Circles\Model\Probes;

use OCA\Circles\Model\Circle;

/**
 * Class CircleProbe
 *
 * @package OCA\Circles\Model\Probes
 */
class CircleProbe extends MemberProbe {
	/**
	 * CircleProbe constructor.
	 */
	public function __construct() {
	}


	/**
	 * Configure whether personal circles are included in the probe
	 *
	 * @param bool $include
	 *
	 * @return $this
	 */
	public function includePersonalCircles(bool $include = true): self
 {
 }

	/**
	 * Configure whether single circles are included in the probe
	 *
	 * @param bool $include
	 *
	 * @return $this
	 */
	public function includeSingleCircles(bool $include = true): self
 {
 }

	/**
	 * Configure whether system circles are included in the probe
	 *
	 * @param bool $include
	 *
	 * @return $this
	 */
	public function includeSystemCircles(bool $include = true): self
 {
 }

	/**
	 * Configure whether hidden circles are included in the probe
	 *
	 * @param bool $include
	 *
	 * @return $this
	 */
	public function includeHiddenCircles(bool $include = true): self
 {
 }

	/**
	 * Configure whether backend circles are included in the probe
	 *
	 * @param bool $include
	 *
	 * @return $this
	 */
	public function includeBackendCircles(bool $include = true): self
 {
 }

	/**
	 * Configure whether non-visible circles are included in the probe
	 *
	 * @param bool $include
	 *
	 * @return $this
	 */
	public function includeNonVisibleCircles(bool $include = true): self
 {
 }

	/**
	 * Return whether non-visible circles are included in the probe
	 *
	 * @return bool
	 */
	public function nonVisibleCirclesIncluded(): bool
 {
 }


	/**
	 * Configure whether single circles are visited in the probe
	 *
	 * @param bool $visit
	 *
	 * @return $this
	 */
	public function visitSingleCircles(bool $visit = true): self
 {
 }

	/**
	 * Return whether single circles are visited in the probe
	 *
	 * @return bool
	 */
	public function visitingSingleCircles(): bool
 {
 }


	/**
	 * Return the include value
	 *
	 * @return int
	 */
	public function included(): int
 {
 }

	/**
	 * Return whether a config is included in the probe (bitwise comparison)
	 *
	 * @param int $config
	 *
	 * @return bool
	 */
	public function isIncluded(int $config): bool
 {
 }


	/**
	 * limit to a specific config
	 *
	 * @param int $config
	 *
	 * @return $this
	 */
	public function limitConfig(int $config = 0): self
 {
 }

	public function hasLimitConfig(): bool
 {
 }

	public function getLimitConfig(): int
 {
 }


	/**
	 * Configure whether personal circles are filtered in the probe
	 *
	 * @param bool $filter
	 *
	 * @return $this
	 */
	public function filterPersonalCircles(bool $filter = true): self
 {
 }

	/**
	 * Configure whether single circles are filtered in the probe
	 *
	 * @param bool $filter
	 *
	 * @return $this
	 */
	public function filterSingleCircles(bool $filter = true): self
 {
 }

	/**
	 * Configure whether system circles are filtered in the probe
	 *
	 * @param bool $filter
	 *
	 * @return $this
	 */
	public function filterSystemCircles(bool $filter = true): self
 {
 }

	/**
	 * Configure whether hidden circles are filtered in the probe
	 *
	 * @param bool $filter
	 *
	 * @return $this
	 */
	public function filterHiddenCircles(bool $filter = true): self
 {
 }

	/**
	 * Configure whether backend circles are filtered in the probe
	 *
	 * @param bool $filter
	 *
	 * @return $this
	 */
	public function filterBackendCircles(bool $filter = true): self
 {
 }


	/**
	 * Add a config to the probe filter
	 *
	 * @param int $config
	 * @param bool $filter
	 *
	 * @return $this
	 */
	public function filterConfig(int $config, bool $filter = true): self
 {
 }


	/**
	 * Return the filtered value
	 *
	 * @return int
	 */
	public function filtered(): int
 {
 }

	/**
	 * Return whether a config is filtered in the probe (bitwise comparison)
	 *
	 * @param int $config
	 *
	 * @return bool
	 */
	public function isFiltered(int $config): bool
 {
 }


	/**
	 * Return an array with includes as options
	 *
	 * @return array
	 */
	public function getAsOptions(): array
 {
 }


	/**
	 * @return string
	 */
	public function getChecksum(): string
 {
 }

	/**
	 * Return a JSON object with includes as options
	 *
	 * @return array
	 */
	public function JsonSerialize(): array
 {
 }
}
