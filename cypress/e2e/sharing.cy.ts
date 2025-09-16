/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	addUserToGroup,
	createGroup,
	createGroupFolder,
	enterFolder,
	fileOrFolderDoesNotExist,
	fileOrFolderExists,
	PERMISSION_DELETE,
	PERMISSION_READ,
	PERMISSION_SHARE,
	PERMISSION_WRITE,
} from './groupfoldersUtils'

import {
	copyFile,
	createFolder,
	createShare,
	moveFile,
} from './files/filesUtils'

import type { User } from '@nextcloud/cypress'
import { randHash } from '../utils'

type SetupInfo = {
	snapshot: string
	user1: User
	user2: User
	groupFolderName1: string
	groupFolderName2: string
}

export function setupSharingTests(): Cypress.Chainable<SetupInfo> {
	return cy.task('getVariable', { key: 'sharing-data' })
		.then((_setupInfo) => {
			const setupInfo = _setupInfo as SetupInfo || {}
			if (setupInfo.snapshot) {
				cy.restoreState(setupInfo.snapshot)
			} else {
				cy.createRandomUser()
					.then(user => {
						setupInfo.user1 = user
					})
				cy.createRandomUser()
					.then(user => {
						setupInfo.user2 = user
					})

				const groupName1 = `test_group_${randHash()}`
				const groupName2 = `test_group_${randHash()}`

				setupInfo.groupFolderName1 = `test_group_folder_${randHash()}`
				createGroup(groupName1)
					.then(() => {
						addUserToGroup(groupName1, setupInfo.user1.userId)
						createGroupFolder(setupInfo.groupFolderName1, groupName1, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_SHARE, PERMISSION_DELETE])
					})
				setupInfo.groupFolderName2 = `test_group_folder_${randHash()}`
				createGroup(groupName2)
					.then(() => {
						addUserToGroup(groupName2, setupInfo.user2.userId)
						createGroupFolder(setupInfo.groupFolderName2, groupName2, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_SHARE, PERMISSION_DELETE])
					})

				cy.then(() => {
					cy.uploadContent(setupInfo.user1, new Blob(['Content of the file']), 'text/plain', `/${setupInfo.groupFolderName1}/file1.txt`)

					cy.login(setupInfo.user1)
					cy.visit('/apps/files')

					createShare(setupInfo.groupFolderName1, setupInfo.user2.userId)
				})

				cy.then(() => {
					cy.saveState()
						.then((value) => {
							setupInfo.snapshot = value
						})
					cy.task('setVariable', { key: 'sharing-data', value: setupInfo })
				})
			}

			return cy.then(() => {
				cy.login(setupInfo.user2)
				cy.visit('/apps/files')
				return cy.wrap(setupInfo)
			})
		})
}

describe('Groupfolders sharing behavior', () => {
	let setupInfo: SetupInfo

	beforeEach(() => {
		setupSharingTests()
			.then((_setupInfo) => {
				setupInfo = _setupInfo
			})
	})

	it('Copy shared groupfolder into another folder', () => {
		createFolder('Target')

		copyFile(setupInfo.groupFolderName1, 'Target')

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder('Target')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderExists('file1.txt')
	})

	it('Copy shared groupfolder into another groupfolder', () => {
		copyFile(setupInfo.groupFolderName1, setupInfo.groupFolderName2)

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName2)
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderExists('file1.txt')
	})

	it('Copy file from shared groupfolder into another folder', () => {
		createFolder('Target')

		enterFolder(setupInfo.groupFolderName1)
		copyFile('file1.txt', '/Target')

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder('Target')
		fileOrFolderExists('file1.txt')
	})

	it('Copy file from shared groupfolder into another groupfolder', () => {
		enterFolder(setupInfo.groupFolderName1)
		copyFile('file1.txt', `/${setupInfo.groupFolderName2}`)

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName2)
		fileOrFolderExists('file1.txt')
	})

	it('Move shared groupfolder into another folder', () => {
		createFolder('Target')

		moveFile(setupInfo.groupFolderName1, 'Target')

		cy.visit('/apps/files')
		fileOrFolderDoesNotExist(setupInfo.groupFolderName1)

		enterFolder('Target')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderExists('file1.txt')
	})

	it('Move file from shared groupfolder into another folder', () => {
		createFolder('Target')

		enterFolder(setupInfo.groupFolderName1)
		moveFile('file1.txt', '/Target')

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderDoesNotExist('file1.txt')

		cy.visit('/apps/files')
		enterFolder('Target')
		fileOrFolderExists('file1.txt')
	})

	it('Move file from shared groupfolder into another groupfolder', () => {
		enterFolder(setupInfo.groupFolderName1)
		moveFile('file1.txt', `/${setupInfo.groupFolderName2}`)

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName1)
		fileOrFolderDoesNotExist('file1.txt')

		cy.visit('/apps/files')
		enterFolder(setupInfo.groupFolderName2)
		fileOrFolderExists('file1.txt')
	})
})
