/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	addUserToGroup,
	createGroup,
	createGroupFolder,
	deleteGroupFolder,
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

import { randHash } from '../utils'

import type { User } from '@nextcloud/cypress'

describe('Groupfolders sharing behavior', () => {
	let user1: User
	let user2: User
	let groupFolderId1: string
	let groupFolderId2: string
	let groupName1: string
	let groupName2: string
	let groupFolderName1: string
	let groupFolderName2: string

	beforeEach(() => {
		if (groupFolderId1) {
			deleteGroupFolder(groupFolderId1)
		}
		if (groupFolderId2) {
			deleteGroupFolder(groupFolderId1)
		}
		groupName1 = `test_group_${randHash()}`
		groupFolderName1 = `test_group_folder_${randHash()}`
		groupName2 = `test_group_${randHash()}`
		groupFolderName2 = `test_group_folder_${randHash()}`

		cy.createRandomUser()
			.then(_user => {
				user1 = _user
			})
		cy.createRandomUser()
			.then(_user => {
				user2 = _user
			})
		createGroup(groupName1)
			.then(() => {
				addUserToGroup(groupName1, user1.userId)
				createGroupFolder(groupFolderName1, groupName1, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_SHARE, PERMISSION_DELETE])
			})
		createGroup(groupName2)
			.then(() => {
				addUserToGroup(groupName2, user2.userId)
				createGroupFolder(groupFolderName2, groupName2, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_SHARE, PERMISSION_DELETE])
			})
	})

	it('Copy shared groupfolder into another folder', () => {
		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName1}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		createShare(groupFolderName1, user2.userId)

		cy.login(user2)
		cy.visit('/apps/files')

		createFolder('Target')

		copyFile(`${groupFolderName1}`, 'Target')

		cy.visit('/apps/files')
		enterFolder(groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder('Target')
		enterFolder(groupFolderName1)
		fileOrFolderExists('file1.txt')
	})

	it('Copy shared groupfolder into another groupfolder', () => {
		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName1}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		createShare(groupFolderName1, user2.userId)

		cy.login(user2)
		cy.visit('/apps/files')

		copyFile(`${groupFolderName1}`, `${groupFolderName2}`)

		cy.visit('/apps/files')
		enterFolder(groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder(groupFolderName2)
		enterFolder(groupFolderName1)
		fileOrFolderExists('file1.txt')
	})

	it('Copy file from shared groupfolder into another folder', () => {
		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName1}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		createShare(groupFolderName1, user2.userId)

		cy.login(user2)
		cy.visit('/apps/files')

		createFolder('Target')

		enterFolder(groupFolderName1)
		copyFile('file1.txt', '/Target')

		cy.visit('/apps/files')
		enterFolder(groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder('Target')
		fileOrFolderExists('file1.txt')
	})

	it('Copy file from shared groupfolder into another groupfolder', () => {
		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName1}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		createShare(groupFolderName1, user2.userId)

		cy.login(user2)
		cy.visit('/apps/files')

		enterFolder(groupFolderName1)
		copyFile('file1.txt', `/${groupFolderName2}`)

		cy.visit('/apps/files')
		enterFolder(groupFolderName1)
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files')
		enterFolder(groupFolderName2)
		fileOrFolderExists('file1.txt')
	})

	it('Move shared groupfolder into another folder', () => {
		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName1}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		createShare(groupFolderName1, user2.userId)

		cy.login(user2)
		cy.visit('/apps/files')

		createFolder('Target')

		moveFile(`${groupFolderName1}`, 'Target')

		cy.visit('/apps/files')
		fileOrFolderDoesNotExist(groupFolderName1)

		enterFolder('Target')
		enterFolder(groupFolderName1)
		fileOrFolderExists('file1.txt')
	})

	it('Move file from shared groupfolder into another folder', () => {
		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName1}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		createShare(groupFolderName1, user2.userId)

		cy.login(user2)
		cy.visit('/apps/files')

		createFolder('Target')

		enterFolder(groupFolderName1)
		moveFile('file1.txt', '/Target')

		cy.visit('/apps/files')
		enterFolder(groupFolderName1)
		fileOrFolderDoesNotExist('file1.txt')

		cy.visit('/apps/files')
		enterFolder('Target')
		fileOrFolderExists('file1.txt')
	})

	it('Move file from shared groupfolder into another groupfolder', () => {
		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName1}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		createShare(groupFolderName1, user2.userId)

		cy.login(user2)
		cy.visit('/apps/files')

		enterFolder(groupFolderName1)
		moveFile('file1.txt', `/${groupFolderName2}`)

		cy.visit('/apps/files')
		enterFolder(groupFolderName1)
		fileOrFolderDoesNotExist('file1.txt')

		cy.visit('/apps/files')
		enterFolder(groupFolderName2)
		fileOrFolderExists('file1.txt')
	})
})
