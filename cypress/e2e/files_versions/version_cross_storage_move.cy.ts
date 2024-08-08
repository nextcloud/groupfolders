/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/cypress'

import { PERMISSION_DELETE, PERMISSION_READ, PERMISSION_WRITE, addUserToGroup, createGroup, createGroupFolder } from '../groupfoldersUtils'
import { assertVersionContent, nameVersion, openVersionsPanel, uploadThreeVersions } from './filesVersionsUtils'
import { clickOnBreadcumbs, closeSidebar, copyFile, moveFile, navigateToFolder } from '../files/filesUtils'

/**
 *
 * @param filePath
 */
function assertVersionsContent(filePath: string) {
	const path = filePath.split('/').slice(0, -1).join('/')

	clickOnBreadcumbs('All files')

	if (path !== '') {
		navigateToFolder(path)
	}

	openVersionsPanel(filePath)

	cy.get('[data-files-versions-version]').should('have.length', 3)
	cy.get('[data-files-versions-version]').eq(2).contains('v1')
	assertVersionContent(0, 'v3')
	assertVersionContent(1, 'v2')
	assertVersionContent(2, 'v1')
	closeSidebar()
}

describe('Versions cross storage move', () => {
	let randomGroupName: string
	let randomGroupFolderName: string
	let randomFileName: string
	let randomCopiedFileName: string
	let user: User

	before(() => {
		randomGroupName = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10)
		randomGroupFolderName = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10)

		cy.createRandomUser().then(_user => { user = _user })
		createGroup(randomGroupName)

		cy.then(() => {
			addUserToGroup(randomGroupName, user.userId)
			createGroupFolder(randomGroupFolderName, randomGroupName, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_DELETE])
		})
	})

	beforeEach(() => {
		const randomString = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10)
		randomFileName = randomString + '.txt'
		randomCopiedFileName = randomString + ' (copy).txt'
		uploadThreeVersions(user, `${randomGroupFolderName}/${randomFileName}`)

		cy.login(user)
		cy.visit('/apps/files')
		navigateToFolder(randomGroupFolderName)
		openVersionsPanel(`${randomGroupFolderName}/${randomFileName}`)
		nameVersion(2, 'v1')
		closeSidebar()
	})

	it('Correctly moves versions to the user\'s FS when the user moves the file out of the groupfolder', () => {
		moveFile(randomFileName, '/')

		assertVersionsContent(randomFileName)

		moveFile(randomFileName, randomGroupFolderName)

		assertVersionsContent(`${randomGroupFolderName}/${randomFileName}`)
	})

	it('Correctly copies versions to the user\'s FS when the user copies the file out of the groupfolder', () => {
		copyFile(randomFileName, '/')

		assertVersionsContent(randomFileName)

		copyFile(randomFileName, randomGroupFolderName)

		assertVersionsContent(`${randomGroupFolderName}/${randomCopiedFileName}`)
	})

	context('When a file is in a subfolder', () => {
		let randomSubFolderName
		let randomCopiedSubFolderName
		let randomSubSubFolderName

		beforeEach(() => {
			const randomString = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10)
			randomSubFolderName = randomString
			randomCopiedSubFolderName = randomString + ' (copy)'

			randomSubSubFolderName = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10)
			clickOnBreadcumbs('All files')
			cy.mkdir(user, `/${randomGroupFolderName}/${randomSubFolderName}`)
			cy.mkdir(user, `/${randomGroupFolderName}/${randomSubFolderName}/${randomSubSubFolderName}`)
			cy.login(user)
			navigateToFolder(randomGroupFolderName)
		})

		it('Correctly moves versions when user moves the containing folder out of the groupfolder', () => {
			moveFile(randomFileName, `${randomSubFolderName}/${randomSubSubFolderName}`)
			moveFile(randomSubFolderName, '/')

			assertVersionsContent(`${randomSubFolderName}/${randomSubSubFolderName}/${randomFileName}`)

			clickOnBreadcumbs('All files')
			moveFile(randomSubFolderName, randomGroupFolderName)

			assertVersionsContent(`${randomGroupFolderName}/${randomSubFolderName}/${randomSubSubFolderName}/${randomFileName}`)
		})

		// TODO: re-enable this test when the copy event from groupfolder to the home storage contains the file list.
		xit('Correctly copies versions when user copies the containing folder out of the groupfolder', () => {
			moveFile(randomFileName, `${randomSubFolderName}/${randomSubSubFolderName}`)
			copyFile(randomSubFolderName, '/')

			assertVersionsContent(`${randomSubFolderName}/${randomSubSubFolderName}/${randomFileName}`)

			clickOnBreadcumbs('All files')
			copyFile(randomSubFolderName, randomGroupFolderName)

			assertVersionsContent(`${randomGroupFolderName}/${randomCopiedSubFolderName}/${randomSubSubFolderName}/${randomFileName}`)
		})
	})
})
