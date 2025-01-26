<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders;

/**
 * @psalm-type GroupFoldersDelegationGroup = array{
 *     gid: string,
 *     displayName: string,
 * }
 *
 * @psalm-type GroupFoldersDelegationCircle = array{
 *     singleId: string,
 *     displayName: string,
 * }
 *
 * @psalm-type GroupFoldersGroup = array{
 *     gid: string,
 *     displayname: string,
 * }
 *
 * @psalm-type GroupFoldersCircle = array{
 *     sid: string,
 *     displayname: string,
 * }
 *
 * @psalm-type GroupFoldersUser = array{
 *     uid: string,
 *     displayname: string,
 * }
 *
 * @psalm-type GroupFoldersAclManage = array{
 *     displayname: string,
 *     id: string,
 *     type: 'user'|'group'|'circle',
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
 *     group_details: array<string, GroupFoldersApplicable>,
 *     groups: array<string, int>,
 *     quota: int,
 *     size: int,
 *     acl: bool,
 *     manage: list<GroupFoldersAclManage>,
 * }
 */
class ResponseDefinitions {
}
