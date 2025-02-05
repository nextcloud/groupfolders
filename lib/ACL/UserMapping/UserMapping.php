<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL\UserMapping;

class UserMapping implements IUserMapping {
	private string $displayName;

	/**
	 * @param 'user'|'group'|'dummy'|'circle' $type
	 */
	public function __construct(
		private string $type,
		private string $id,
		?string $displayName = null,
	) {
		$this->displayName = $displayName ?? $id;
	}

	/** @return 'user'|'group'|'dummy'|'circle' */
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
