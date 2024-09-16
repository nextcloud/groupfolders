<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Mount;

use OCP\Files\Storage\IDisableEncryptionStorage;

/**
 * @psalm-suppress UnimplementedInterfaceMethod
 * Psalm gets confused about missing methods, but those are implemented in OC\Files\Storage\Wrapper\Wrapper,
 * so this suppression is fine and necessary as there is nothing wrong.
 */
class GroupFolderNoEncryptionStorage extends GroupFolderStorage implements IDisableEncryptionStorage {
}
