<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL\UserMapping;

class UserMapping implements IUserMapping {
	private readonly string $displayName;

	/**
	 * @param 'user'|'group'|'dummy'|'circle' $type
	 */
	public function __construct(
		private readonly string $type,
		private readonly string $id,
		?string $displayName = null,
	) {
		$this->displayName = $displayName ?? $id;
	}

	public function getType(): string {
		return $this->type;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getDisplayName(): string {
		return $this->displayName;
	}

	public function getKey(): string {
		return $this->getType() . ':' . $this->getId();
	}
}
