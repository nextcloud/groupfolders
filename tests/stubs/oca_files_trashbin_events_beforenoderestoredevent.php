<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Trashbin\Events;

use OCP\Exceptions\AbortedEventException;
use OCP\Files\Events\Node\AbstractNodesEvent;
use OCP\Files\Node;

/**
 * @since 28.0.0
 */
class BeforeNodeRestoredEvent extends AbstractNodesEvent {
	public function __construct(Node $source, Node $target, bool &$run)
    {
    }

	/**
	 * @since 28.0.0
	 * @deprecated 29.0.0 - use OCP\Exceptions\AbortedEventException instead
	 */
	public function abortOperation(?\Throwable $ex = null)
    {
    }
}
