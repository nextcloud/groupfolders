/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/cypress'

import { assertVersionContent, doesNotHaveAction, openVersionsPanel, restoreVersion, uploadThreeVersions } from './filesVersionsUtils'
import { PERMISSION_DELETE, PERMISSION_READ, PERMISSION_WRITE, addUserToGroup, createGroup, createGroupFolder } from '../groupfoldersUtils'
import { navigateToFolder } from '../files/filesUtils'

describe('Versions restoration', () => {
	let randomGroupName: string
	let randomGroupFolderName: string
	let randomFileName: string
	let randomFilePath: string
	let user1: User

	before(() => {
		randomGroupName = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10)
		randomGroupFolderName = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10)
		randomFileName = Math.random().toString(36).replace(/[^a-z]+/g, '').substring(0, 10) + '.txt'
		randomFilePath = `${randomGroupFolderName}/${randomFileName}`

		cy.createRandomUser().then(_user => { user1 = _user })
		createGroup(randomGroupName)

		cy.then(() => {
			addUserToGroup(randomGroupName, user1.userId)
			createGroupFolder(randomGroupFolderName, randomGroupName, [PERMISSION_READ, PERMISSION_WRITE, PERMISSION_DELETE])

			uploadThreeVersions(user1, randomFilePath)
			cy.login(user1)
		})

		cy.visit('/apps/files')
		navigateToFolder(randomGroupFolderName)
		openVersionsPanel(randomFilePath)
	})

	it('Current version does not have restore action', () => {
		doesNotHaveAction(0, 'restore')
	})

	it('Restores initial version', () => {
		restoreVersion(2)

		cy.get('#tab-version_vue').within(() => {
			cy.get('[data-files-versions-version]').should('have.length', 3)
			cy.get('[data-files-versions-version]').eq(0).contains('Current version')
			cy.get('[data-files-versions-version]').eq(2).contains('Initial version').should('not.exist')
		})
	})

	it('Downloads versions and assert there content', () => {
		assertVersionContent(0, 'v1')
		assertVersionContent(1, 'v3')
		assertVersionContent(2, 'v2')
	})
})
