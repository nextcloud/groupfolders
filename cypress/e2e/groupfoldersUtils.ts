/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable jsdoc/require-jsdoc */

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

export function enableEncryptionModule() {
	return cy.runOccCommand('app:enable encryption')
}

export function disableEncryptionModule() {
	return cy.runOccCommand('app:disable encryption')
}

export function enableEncryption() {
	return cy.runOccCommand('config:app:set --value=yes core encryption_enabled')
}

export function disableEncryption() {
	return cy.runOccCommand('config:app:delete core encryption_enabled')
}

export function enableHomeStorageEncryption() {
	// Default value is enabled
	return cy.runOccCommand('config:app:delete encryption encryptHomeStorage')
}

export function disableHomeStorageEncryption() {
	return cy.runOccCommand('config:app:set --value=0 encryption encryptHomeStorage')
}

export function enableGroupfoldersEncryption() {
	return cy.runOccCommand('config:app:set --value=true groupfolders enable_encryption')
}

export function disableGroupfoldersEncryption() {
	return cy.runOccCommand('config:app:delete groupfolders enable_encryption')
}

export function fileOrFolderExists(name: string) {
	// Make sure file list is loaded first
	cy.get('[data-cy-files-list-tfoot],[data-cy-files-content-empty]').should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"]`).should('be.visible')
}

export function fileOrFolderDoesNotExist(name: string) {
	// Make sure file list is loaded first
	cy.get('[data-cy-files-list-tfoot],[data-cy-files-content-empty]').should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"]`).should('not.exist')
}

export function fileOrFolderExistsInTrashbin(name: string) {
	// Make sure file list is loaded first
	cy.get('[data-cy-files-list-tfoot],[data-cy-files-content-empty]').should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"]`).should('be.visible')
}

export function fileOrFolderDoesNotExistInTrashbin(name: string) {
	// Make sure file list is loaded first
	cy.get('[data-cy-files-list-tfoot],[data-cy-files-content-empty]').should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"]`).should('not.exist')
}

export function enterFolder(name: string) {
	cy.intercept({ times: 1, method: 'PROPFIND', url: `**/dav/files/**/${name}` }).as('propFindFolder')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"]`).click()
	cy.wait('@propFindFolder')
}

export function enterFolderInTrashbin(name: string) {
	cy.intercept({ times: 1, method: 'PROPFIND', url: `**/dav/trashbin/**/${name}.d*` }).as('propFindFolder')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"] [data-cy-files-list-row-name]`).click()
	cy.wait('@propFindFolder')
}

export function deleteFolder(name: string) {
	cy.intercept({ times: 1, method: 'DELETE', url: `**/dav/files/**/${name}` }).as('delete')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"] [data-cy-files-list-row-actions]`).click()
	cy.get('[data-cy-files-list] [data-cy-files-list-row-action="delete"]').should('be.visible')
	cy.get('[data-cy-files-list] [data-cy-files-list-row-action="delete"]').scrollIntoView()
	cy.get('[data-cy-files-list] [data-cy-files-list-row-action="delete"]').click()
	cy.wait('@delete').its('response.statusCode').should('eq', 204)
}

export function deleteFolderFromTrashbin(name: string) {
	cy.intercept({ times: 1, method: 'DELETE', url: `**/dav/trashbin/**/${name}.d*` }).as('delete')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"] [data-cy-files-list-row-actions] button:not([data-cy-files-list-row-action])`).click()
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).scrollIntoView()
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).click()
	cy.wait('@delete').its('response.statusCode').should('eq', 204)
}

export function deleteFile(name: string) {
	cy.intercept({ times: 1, method: 'DELETE', url: `**/dav/files/**/${name}` }).as('delete')
	// For files wait for preview to load and release lock
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"] .files-list__row-icon img`)
		.should('be.visible')
		.and(($img) => {
		// "naturalWidth" and "naturalHeight" are set when the image loads
			expect($img[0].naturalWidth, 'image has natural width').to.be.greaterThan(0)
		})
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${name}"] [data-cy-files-list-row-actions]`).click()
	cy.get('[data-cy-files-list] [data-cy-files-list-row-action="delete"]').should('be.visible')
	cy.get('[data-cy-files-list] [data-cy-files-list-row-action="delete"]').scrollIntoView()
	cy.get('[data-cy-files-list] [data-cy-files-list-row-action="delete"]').click()
	cy.wait('@delete').its('response.statusCode').should('eq', 204)
}

export function deleteFileFromTrashbin(name: string) {
	cy.intercept({ times: 1, method: 'DELETE', url: `**/dav/trashbin/**/${name}.d*` }).as('delete')
	// For files wait for preview to load and release lock
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"] .files-list__row-icon img`)
	.should('be.visible')
	.and(($img) => {
		// "naturalWidth" and "naturalHeight" are set when the image loads
		expect($img[0].naturalWidth, 'image has natural width').to.be.greaterThan(0)
	})
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"] [data-cy-files-list-row-actions] button:not([data-cy-files-list-row-action])`).click()
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).should('be.visible')
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).scrollIntoView()
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).click()
	cy.wait('@delete').its('response.statusCode').should('eq', 204)
}

export function restoreFile(name: string) {
	cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="${name}.d"] [data-cy-files-list-row-action="restore"]`).click()
}
