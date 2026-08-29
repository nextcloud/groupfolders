<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Folder;

/**
 * Resolves the display name (mount point) of Team folders by id, for other
 * apps that encounter a Team folder through its storage path
 * ("__groupfolders/<id>/…") — file cache scans, share audits, reports — and
 * want to show the folder's name instead of its numeric id.
 *
 * This is the stable contract for other apps. Resolve it from the container
 * after checking that the interface exists, so the consuming app keeps
 * working when Team folders is not installed:
 *
 *   if (interface_exists(IFolderNameResolver::class)) {
 *       $names = $this->container->get(IFolderNameResolver::class)->getMountPoints($ids);
 *   }
 *
 * @since 24.0.0
 */
interface IFolderNameResolver {
	/**
	 * The mount point of one Team folder, or null if no folder has this id.
	 */
	public function getMountPoint(int $folderId): ?string;

	/**
	 * The mount points of many Team folders in one query. Ids without a
	 * folder are absent from the result.
	 *
	 * @param list<int> $folderIds
	 * @return array<int, string> folder id to mount point
	 */
	public function getMountPoints(array $folderIds): array;
}
