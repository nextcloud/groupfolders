<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Storage;

use OCP\Files;
use OCP\ITempManager;
use OCP\Server;

/**
 * local storage backend in temporary folder for testing purpose
 */
class Temporary extends Local {
	public function __construct(array $parameters = [])
 {
 }

	public function cleanUp(): void
 {
 }

	public function __destruct()
 {
 }

	public function getDataDir(): array|string
 {
 }
}
