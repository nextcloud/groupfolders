<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Config;

use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

class ConfigLexicon implements ILexicon {
	public const string USER_NEEDS_MOUNT_REFRESH = 'user_needs_mount_refresh';

	#[\Override]
	public function getStrictness(): Strictness {
		return Strictness::IGNORE;
	}

	#[\Override]
	public function getAppConfigs(): array {
		return [];
	}

	#[\Override]
	public function getUserConfigs(): array {
		return [
			new Entry(self::USER_NEEDS_MOUNT_REFRESH, ValueType::BOOL, true, 'whether a user needs to have the groupfolder mount data refreshed for possible changes'),
		];
	}
}
