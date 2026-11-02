/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'

import { occ, occCleanup } from './occ.ts'

/** Permissions a group can be granted on a team folder. */
export type TeamFolderPermission = 'read' | 'write' | 'share' | 'delete'

/** An advanced permission rule, granting (`+`) or denying (`-`) a permission. */
export type AclRule = `${'+' | '-'}${'read' | 'write' | 'create' | 'delete' | 'share'}`

/**
 * A random lowercase string to build unique names from, so tests running in
 * parallel never share a group or a team folder.
 */
export function randomName(): string {
	return Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)
}

/**
 * Create a group with the given members.
 *
 * @param groupId - Id of the new group
 * @param members - Users to add to the group
 */
export async function createGroup(groupId: string, members: User[]): Promise<void> {
	await occ(['group:add', groupId])
	for (const member of members) {
		await occ(['group:adduser', groupId, member.userId])
	}
}

/**
 * Delete a group, ignoring failures.
 *
 * @param groupId - Id of the group
 */
export async function deleteGroup(groupId: string): Promise<void> {
	await occCleanup(['group:delete', groupId])
}

/**
 * Create a team folder and grant a group access to it.
 *
 * @param mountPoint - Mount point of the folder, relative to the root of the members' files
 * @param groupId - Group to grant access to
 * @param permissions - Permissions of the group
 * @return The id of the new team folder
 */
export async function createTeamFolder(mountPoint: string, groupId: string, permissions: TeamFolderPermission[]): Promise<number> {
	const id = Number.parseInt(await occ(['groupfolders:create', mountPoint]), 10)
	if (Number.isNaN(id)) {
		throw new Error(`Creating the team folder ${mountPoint} did not return its id`)
	}
	await occ(['groupfolders:group', String(id), groupId, ...permissions])
	return id
}

/**
 * Delete a team folder including its contents, ignoring failures.
 *
 * @param id - Id of the team folder
 */
export async function deleteTeamFolder(id: number): Promise<void> {
	await occCleanup(['groupfolders:delete', '--force', String(id)])
}

/**
 * Enable advanced permissions (ACLs) for a team folder.
 *
 * @param id - Id of the team folder
 */
export async function enableAdvancedPermissions(id: number): Promise<void> {
	await occ(['groupfolders:permissions', String(id), '--enable'])
}

/**
 * Allow a user to manage the advanced permissions of a team folder.
 *
 * @param id - Id of the team folder
 * @param user - The new manager
 */
export async function addAclManager(id: number, user: User): Promise<void> {
	await occ(['groupfolders:permissions', String(id), '--manage-add', '--user', user.userId])
}

/**
 * Set advanced permission rules for a user on a path of a team folder.
 *
 * @param id - Id of the team folder
 * @param path - Path within the team folder, e.g. `/subfolder`
 * @param user - User the rules apply to
 * @param rules - The rules, e.g. `['+read', '-write']`
 */
export async function setAclPermissions(id: number, path: string, user: User, rules: AclRule[]): Promise<void> {
	await occ(['groupfolders:permissions', String(id), path, '--user', user.userId, '--', ...rules])
}
