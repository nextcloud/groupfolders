<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\ACL\UserMapping;

interface IUserMapping {
	/** @return 'user'|'group' */
	public function getType(): string;

	public function getId(): string;

	public function getDisplayName(): string;
}
