<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders;

/**
 * @psalm-type GroupFoldersGroup = array{
 *     gid: string,
 *     displayName: string,
 * }
 *
 * @psalm-type GroupFoldersUser = array{
 *     uid: string,
 *     displayName: string,
 * }
 *
 * @psalm-type GroupFoldersCircle = array{
 *     singleId: string,
 *     displayName: string,
 * }
 *
 * @psalm-type GroupFoldersAclManage = array{
 *     displayName: string,
 *     id: string,
 *     type: 'user'|'group',
 * }
 *
 * @psalm-type GroupFoldersApplicable = array{
 *     displayName: string,
 *     permissions: int,
 *     type: 'group'|'circle',
 * }
 *
 * @psalm-type GroupFoldersFolder = array{
 *     id: int,
 *     mount_point: string,
 *     groups: array<string, GroupFoldersApplicable>|\stdClass,
 *     quota: int,
 *     size: int,
 *     acl: bool,
 *     manage: list<GroupFoldersAclManage>,
 * }
 */
class ResponseDefinitions {
}
