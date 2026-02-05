<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders;

/**
 * @phpstan-type GroupFoldersDelegationGroup = array{
 *     gid: string,
 *     displayName: string,
 * }
 *
 * @phpstan-type GroupFoldersDelegationCircle = array{
 *     singleId: string,
 *     displayName: string,
 * }
 *
 * @phpstan-type GroupFoldersGroup = array{
 *     gid: string,
 *     displayname: string,
 * }
 *
 * @phpstan-type GroupFoldersCircle = array{
 *     sid: string,
 *     displayname: string,
 * }
 *
 * @phpstan-type GroupFoldersUser = array{
 *     uid: string,
 *     displayname: string,
 * }
 *
 * @phpstan-type GroupFoldersAclManage = array{
 *     displayname: string,
 *     id: string,
 *     type: 'user'|'group'|'circle',
 * }
 *
 * @phpstan-type GroupFoldersApplicable = array{
 *     displayName: string,
 *     permissions: int,
 *     type: 'group'|'circle',
 * }
 *
 * @phpstan-type GroupFoldersFolder = array{
 *     id: int,
 *     mount_point: string,
 *     group_details: array<string, GroupFoldersApplicable>,
 *     groups: array<string, int>,
 *     quota: int,
 *     size: int,
 *     acl: bool,
 *     acl_default_no_permission: bool,
 *     manage: list<GroupFoldersAclManage>,
 *     sortIndex?: int,
 * }
 */
class ResponseDefinitions {
}
