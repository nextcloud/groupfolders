/* eslint-disable jsdoc/require-jsdoc */
/**
 * @copyright Copyright (c) 2023 Louis Chmn <louis@chmn.me>
 *
 * @author Louis Chmn <louis@chmn.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

export const PERMISSION_READ = 'read'
export const PERMISSION_WRITE = 'write'
export const PERMISSION_SHARE = 'share'
export const PERMISSION_DELETE = 'delete'

export function createGroup(groupName: string) {
	return cy.runOccCommand(`group:add ${groupName}`)
}

export function addUserToGroup(groupName: string, userName: string) {
	return cy.runOccCommand(`group:adduser ${groupName} ${userName}`)
}

export function createGroupFolder(groupFolderName: string, groupName: string, groupPermissions: string[]) {
	return cy.runOccCommand(`groupfolders:create ${groupFolderName}`)
		.then(execObject => {
			const groupFolderId = execObject.stdout
			return cy.runOccCommand(`groupfolders:group ${groupFolderId} ${groupName} ${groupPermissions.join(' ')}`)
				.then(() => groupFolderId)
		})
}

export function enableACLPermissions(groupFolderId: string) {
	return cy.runOccCommand(`groupfolders:permissions ${groupFolderId} --enable`)
}

export function disableACLPermissions(groupFolderId: string) {
	return cy.runOccCommand(`groupfolders:permissions ${groupFolderId} --disable`)
}

export function addACLManager(groupFolderId: string, groupOrUserName: string) {
	return cy.runOccCommand(`groupfolders:permissions ${groupFolderId} --manage-add ${groupOrUserName}`)
}

export function removeACLManager(groupFolderId: string, groupOrUserName: string) {
	return cy.runOccCommand(`groupfolders:permissions ${groupFolderId} --manage-remove ${groupOrUserName}`)
}

export function setACLPermissions(
	groupFolderId: string,
	path: string,
	aclPermissions: string[],
	groupId?: string,
	userId?: string,
) {
	const target = groupId !== undefined ? `--group ${groupId}` : `--user ${userId}`
	return cy.runOccCommand(`groupfolders:permissions ${groupFolderId} ${path} ${aclPermissions} ${target}`)
}

export function deleteGroupFolder(groupFolderId: string) {
	return cy.runOccCommand(`groupfolders:delete ${groupFolderId}`)
}
