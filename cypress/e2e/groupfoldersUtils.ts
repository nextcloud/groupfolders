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

export function addACLManagerUser(groupFolderId: string, userName: string) {
	return cy.runOccCommand(`groupfolders:permissions ${groupFolderId} --manage-add --user ${userName}`)
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
	return cy.runOccCommand(`groupfolders:permissions ${groupFolderId} ${path} ${target} -- ${aclPermissions.join(' ')}`)
}

export function deleteGroupFolder(groupFolderId: string) {
	return cy.runOccCommand(`groupfolders:delete ${groupFolderId}`)
}

export function fileOrFolderExists(name: string) {
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"]`).should('be.visible')
}

export function fileOrFolderDoesNotExist(name: string) {
	// Make sure file list is loaded first
	cy.get(`[data-cy-files-list-tfoot],[data-cy-files-content-empty]`).should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"]`).should('not.exist')
}

export function fileOrFolderExistsInTrashbin(name: string) {
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"]`).should('be.visible')
}

export function fileOrFolderDoesNotExistInTrashbin(name: string) {
	// Make sure file list is loaded first
	cy.get(`[data-cy-files-list-tfoot],[data-cy-files-content-empty]`).should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"]`).should('not.exist')
}

export function enterFolder(name: string) {
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"]`).click()
}

export function enterFolderInTrashbin(name: string) {
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"]`).click()
}

export function deleteFile(name: string) {
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"] [data-cy-files-list-row-actions]`).click()
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).scrollIntoView()
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).click()
}

export function restoreFile(name: string) {
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"] [data-cy-files-list-row-action="restore"]`).click()
}
