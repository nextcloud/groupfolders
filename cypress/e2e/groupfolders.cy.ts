/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	addACLManagerUser,
	addUserToGroup,
	createGroup,
	createGroupFolder,
	deleteGroupFolder,
	deleteFile,
	deleteFileFromTrashbin,
	deleteFolder,
	deleteFolderFromTrashbin,
	enableACLPermissions,
	enterFolder,
	enterFolderInTrashbin,
	fileOrFolderExists,
	fileOrFolderDoesNotExist,
	fileOrFolderExistsInTrashbin,
	fileOrFolderDoesNotExistInTrashbin,
	restoreFile,
	setACLPermissions,
	PERMISSION_DELETE,
	PERMISSION_READ,
	PERMISSION_WRITE,
} from './groupfoldersUtils'

import { randHash } from '../utils'

import type { User } from '@nextcloud/cypress'

describe('Groupfolders ACLs and trashbin behavior', () => {
	let user1: User
	let user2: User
	let managerUser: User
	let groupFolderId: string
	let groupName: string
	let groupFolderName: string

	beforeEach(() => {
		if (groupFolderId) {
			deleteGroupFolder(groupFolderId)
		}
		groupName = `test_group_${randHash()}`
		groupFolderName = `test_group_folder_${randHash()}`

		cy.createRandomUser()
			.then(_user => {
				user1 = _user
			})
		cy.createRandomUser()
			.then(_user => {
				user2 = _user
			})
		cy.createRandomUser()
			.then(_user => {
				managerUser = _user

				createGroup(groupName)
					.then(() => {
						addUserToGroup(groupName, user1.userId)
						addUserToGroup(groupName, user2.userId)
						addUserToGroup(groupName, managerUser.userId)
						createGroupFolder(groupFolderName, groupName, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_DELETE])
							.then(_groupFolderId => {
								groupFolderId = _groupFolderId
								enableACLPermissions(groupFolderId)
								addACLManagerUser(groupFolderId,managerUser.userId)
							})
					})
			})
	})

	it('ACL, delete and restore', () => {
		// Create two subfolders and two files as manager
		cy.login(managerUser)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1`)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1/subfolder2`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/file1.txt`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/subfolder2/file2.txt`)

		// Set ACL permissions
		setACLPermissions(groupFolderId, '/subfolder1', [`+${PERMISSION_READ}`,`-${PERMISSION_WRITE}`], undefined, user1.userId)
		setACLPermissions(groupFolderId, '/subfolder1', [`-${PERMISSION_READ}`], undefined, user2.userId)

		// User1 has access
		cy.logout()
		cy.login(user1)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		enterFolder('subfolder1')
		fileOrFolderExists('file1.txt')
		enterFolder('subfolder2')
		fileOrFolderExists('file2.txt')

		// User2 has no access
		cy.logout()
		cy.login(user2)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		fileOrFolderDoesNotExist('subfolder1')

		// Delete files
		cy.log('Deleting the files')
		cy.logout()
		cy.login(managerUser)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		enterFolder('subfolder1')
		deleteFile('file1.txt')
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		enterFolder('subfolder1')
		deleteFolder('subfolder2')

		// User1 sees it in trash
		cy.logout()
		cy.login(user1)
		cy.visit('/apps/files/trashbin')
		fileOrFolderExistsInTrashbin('file1.txt')
		enterFolderInTrashbin('subfolder2')
		fileOrFolderExists('file2.txt')

		// User2 does not
		cy.logout()
		cy.login(user2)
		cy.visit('/apps/files/trashbin')
		fileOrFolderDoesNotExistInTrashbin('file1.txt')
		fileOrFolderDoesNotExistInTrashbin('subfolder2')

		// Restore files
		cy.log('Restoring the files')
		cy.logout()
		cy.login(managerUser)
		cy.visit('/apps/files/trashbin')
		fileOrFolderExistsInTrashbin('file1.txt')
		fileOrFolderExistsInTrashbin('subfolder2')
		restoreFile('file1.txt')
		restoreFile('subfolder2')

		// User1 has access
		cy.logout()
		cy.login(user1)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		fileOrFolderExists('subfolder1')
		enterFolder('subfolder1')
		fileOrFolderExists('file1.txt')
		enterFolder('subfolder2')
		fileOrFolderExists('file2.txt')

		// User2 has no access
		cy.logout()
		cy.login(user2)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		fileOrFolderDoesNotExist('subfolder1')
	})

	it('ACL directly on deleted folder', () => {
		// Create a subfolders and a file as manager
		cy.login(managerUser)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/file1.txt`)

		// Set ACL permissions on subfolder
		setACLPermissions(groupFolderId, '/subfolder1', [`+${PERMISSION_READ}`,`-${PERMISSION_WRITE}`], undefined, user1.userId)
		setACLPermissions(groupFolderId, '/subfolder1', [`-${PERMISSION_READ}`], undefined, user2.userId)

		// Delete subfolder
		cy.login(managerUser)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		deleteFolder('subfolder1')

		// User1 sees it in trash
		cy.login(user1)
		cy.visit('/apps/files/trashbin')
		fileOrFolderExistsInTrashbin('subfolder1')
		enterFolderInTrashbin('subfolder1')
		fileOrFolderExists('file1.txt')

		// User2 does not
		cy.login(user2)
		cy.visit('/apps/files/trashbin')
		fileOrFolderDoesNotExistInTrashbin('subfolder1')
	})

	it('Delete, rename parent and restore', () => {
		// Create a subfolders and a file as manager
		cy.login(managerUser)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/file1.txt`)

		// Delete file
		cy.logout()
		cy.login(managerUser)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		enterFolder('subfolder1')
		deleteFile('file1.txt')

		// Rename subfolder1
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"] [data-cy-files-list-row-actions]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="rename"]`).scrollIntoView()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="rename"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"] [class="files-list__row-rename"] [class="input-field__input"]`).type('subfolder1renamed{enter}')
		fileOrFolderExists('subfolder1renamed')

		// Restore from trash
		cy.visit('/apps/files/trashbin')
		restoreFile('file1.txt')

		// File should be restored in renamed folder
		cy.login(managerUser)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		fileOrFolderExists('subfolder1renamed')
		fileOrFolderDoesNotExist('file1.txt')
		enterFolder('subfolder1renamed')
		fileOrFolderExists('file1.txt')
	})

	it('Delete, rename parent and check ACL', () => {
		// Create a subfolders and a file as manager
		cy.login(managerUser)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/file1.txt`)

		// Set ACL permissions
		setACLPermissions(groupFolderId, '/subfolder1', [`+${PERMISSION_READ}`,`-${PERMISSION_WRITE}`], undefined, user1.userId)
		setACLPermissions(groupFolderId, '/subfolder1', [`-${PERMISSION_READ}`], undefined, user2.userId)

		// Delete file
		cy.logout()
		cy.login(managerUser)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		enterFolder('subfolder1')
		deleteFile('file1.txt')

		// Rename subfolder1
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"] [data-cy-files-list-row-actions]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="rename"]`).scrollIntoView()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="rename"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"] [class="files-list__row-rename"] [class="input-field__input"]`).type('subfolder1renamed{enter}')
		fileOrFolderExists('subfolder1renamed')

		// User1 sees it in trash
		cy.login(user1)
		cy.visit('/apps/files/trashbin')
		fileOrFolderExistsInTrashbin('file1.txt')

		// User2 does not
		cy.login(user2)
		cy.visit('/apps/files/trashbin')
		fileOrFolderDoesNotExistInTrashbin('file1.txt')
	})

	it('ACL, delete and delete from trash', () => {
		// Create two subfolders and two files as manager
		cy.login(managerUser)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1`)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1/subfolder2`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/file1.txt`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/subfolder2/file2.txt`)

		// Set ACL permissions
		setACLPermissions(groupFolderId, '/subfolder1', [`+${PERMISSION_READ}`,`-${PERMISSION_WRITE}`], undefined, user1.userId)
		setACLPermissions(groupFolderId, '/subfolder1', [`-${PERMISSION_READ}`], undefined, user2.userId)

		// Delete files
		cy.log('Deleting the files')
		cy.logout()
		cy.login(managerUser)
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		enterFolder('subfolder1')
		deleteFile('file1.txt')
		cy.visit('/apps/files')
		enterFolder(groupFolderName)
		enterFolder('subfolder1')
		deleteFolder('subfolder2')

		// Delete files from trash
		cy.log('Deleting the files permanently')
		cy.logout()
		cy.login(managerUser)
		cy.visit('/apps/files/trashbin')
		fileOrFolderExistsInTrashbin('file1.txt')
		deleteFileFromTrashbin('file1.txt')
		fileOrFolderExistsInTrashbin('subfolder2')
		deleteFolderFromTrashbin('subfolder2')
		fileOrFolderDoesNotExistInTrashbin('file1.txt')
		fileOrFolderDoesNotExistInTrashbin('subfolder2')
	})
})
