<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL\UserMapping;

class UserMapping implements IUserMapping {
	/** @var 'user'|'group' * */
	private string $type;
	private string $id;
	private string $displayName;

	public function __construct(string $type, string $id, ?string $displayName = null) {
		$this->type = $type;
		$this->id = $id;
		$this->displayName = $displayName ?? $id;
	}

	/** @return 'user'|'group' */
	public function getType(): string {
		return $this->type;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getDisplayName(): string {
		return $this->displayName;
	}
}
