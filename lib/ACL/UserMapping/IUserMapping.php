<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL\UserMapping;

interface IUserMapping {
	/** @return 'user'|'group'|'dummy'|'circle' */
	public function getType(): string;

	public function getId(): string;

	public function getDisplayName(): string;

	public function getKey(): string;
}
