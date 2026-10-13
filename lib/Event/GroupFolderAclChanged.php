<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Event;

use OCA\GroupFolders\ACL\Rule;
use OCP\EventDispatcher\Event;

/**
 * Event fired when advanced permissions are changed
 */
class GroupFolderAclChanged extends Event {
	public const string NEW_RULE = 'new_rule';
	public const string UPDATE_RULE = 'update_rule';
	public const string DELETE_RULE = 'remove_rule';

	public function __construct(
		public string $action,
		public Rule $rule,
	) {
		parent::__construct();
	}

	public function getAction(): string {
		return $this->action;
	}

	public function getRule(): Rule {
		return $this->rule;
	}
}
