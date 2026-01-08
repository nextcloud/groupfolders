<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAV\Events;

use OCP\EventDispatcher\Event;
use Sabre\DAV\Server;

class SabrePluginAddEvent extends Event {

	public function __construct(Server $server) {}

	public function getServer(): Server {}
}
