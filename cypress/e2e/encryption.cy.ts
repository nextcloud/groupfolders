/**
 * @copyright Copyright (c) 2024 Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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
import {
	addUserToGroup,
	createGroup,
	createGroupFolder,
	deleteGroupFolder,
	disableEncryption,
	disableEncryptionModule,
	disableGroupfoldersEncryption,
	disableHomeStorageEncryption,
	enableEncryption,
	enableEncryptionModule,
	enableGroupfoldersEncryption,
	enableHomeStorageEncryption,
	enterFolder,
	fileOrFolderExists,
	PERMISSION_DELETE,
	PERMISSION_READ,
	PERMISSION_WRITE,
} from './groupfoldersUtils'

import {
	assertFileContent,
	moveFile,
} from './files/filesUtils'

import { randHash } from '../utils'

import type { User } from '@nextcloud/cypress'

describe('Groupfolders encryption behavior', () => {
	let user1: User
	let groupFolderId: string
	let groupName: string
	let groupFolderName: string

	before(() => {
		enableEncryptionModule()
		enableEncryption()
	})

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
		createGroup(groupName)
			.then(() => {
				addUserToGroup(groupName, user1.userId)
				createGroupFolder(groupFolderName, groupName, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_DELETE])
			})
	})

	after(() => {
		// Restore default values
		disableGroupfoldersEncryption()
		enableHomeStorageEncryption()
		disableEncryption()
		disableEncryptionModule()
	})

	it('Move file from encrypted storage to encrypted groupfolder', () => {
		enableHomeStorageEncryption()
		enableGroupfoldersEncryption()

		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', '/file1.txt')

		cy.login(user1)
		cy.visit('/apps/files')

		moveFile('file1.txt', groupFolderName)

		enterFolder(groupFolderName)
		fileOrFolderExists('file1.txt')
		assertFileContent('file1.txt', 'Content of the file')
	})

	it('Move file from encrypted storage to non encrypted groupfolder', () => {
		enableHomeStorageEncryption()
		disableGroupfoldersEncryption()

		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', '/file1.txt')

		cy.login(user1)
		cy.visit('/apps/files')

		moveFile('file1.txt', groupFolderName)

		enterFolder(groupFolderName)
		fileOrFolderExists('file1.txt')
		assertFileContent('file1.txt', 'Content of the file')
	})

	it('Move file from non encrypted storage to encrypted groupfolder', () => {
		disableHomeStorageEncryption()
		enableGroupfoldersEncryption()

		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', '/file1.txt')

		cy.login(user1)
		cy.visit('/apps/files')

		moveFile('file1.txt', groupFolderName)

		enterFolder(groupFolderName)
		fileOrFolderExists('file1.txt')
		assertFileContent('file1.txt', 'Content of the file')
	})

	it('Move file from encrypted groupfolder to encrypted storage', () => {
		enableHomeStorageEncryption()
		enableGroupfoldersEncryption()

		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		enterFolder(groupFolderName)
		moveFile('file1.txt', '/')

		cy.visit('/apps/files')
		fileOrFolderExists('file1.txt')
		assertFileContent('file1.txt', 'Content of the file')
	})

	it('Move file from encrypted groupfolder to non encrypted storage', () => {
		disableHomeStorageEncryption()
		enableGroupfoldersEncryption()

		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		enterFolder(groupFolderName)
		moveFile('file1.txt', '/')

		cy.visit('/apps/files')
		fileOrFolderExists('file1.txt')
		assertFileContent('file1.txt', 'Content of the file')
	})

	it('Move file from non encrypted groupfolder to encrypted storage', () => {
		enableHomeStorageEncryption()
		disableGroupfoldersEncryption()

		cy.uploadContent(user1, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/file1.txt`)

		cy.login(user1)
		cy.visit('/apps/files')

		enterFolder(groupFolderName)
		moveFile('file1.txt', '/')

		cy.visit('/apps/files')
		fileOrFolderExists('file1.txt')
		assertFileContent('file1.txt', 'Content of the file')
	})
})
