<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OCP\Files\Storage\IDisableEncryptionStorage;

class GroupFolderNoEncryptionStorage extends GroupFolderStorage implements IDisableEncryptionStorage {
}
