<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCP\Console\Attribute\AsCommand;
use OCP\Console\ExitCode;
use OCP\Console\IOutput;

/**
 * Base class for the group folder expiration commands.
 *
 * The actual instance used at runtime depends on whether the versions and/or trashbin
 * apps are enabled, see OCA\GroupFolders\AppInfo\Application::register().
 */
#[AsCommand(
	name: 'groupfolders:expire',
	description: 'Trigger expiration for files stored in Team folders (trash and/or versions)',
)]
class ExpireGroupBase {
	public function __invoke(IOutput $output): ExitCode {
		$output->writeln('<error>groupfolder expiration handling is currently disabled because there is nothing to expire. Enable the "Delete Files" or/and "Versions" app to enable this feature.</error>');
		return ExitCode::Success;
	}
}
