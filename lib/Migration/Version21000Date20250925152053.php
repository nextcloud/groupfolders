<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Migration;

use Closure;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version21000Date20250925152053 extends SimpleMigrationStep {
	public function __construct(
		private readonly FolderManager $folderManager,
	) {
	}

	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->folderManager->updateOverwriteHomeFolders();
	}
}
