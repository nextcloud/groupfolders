/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/cypress'

import { assertVersionContent, nameVersion, openVersionsPanel, uploadThreeVersions } from './filesVersionsUtils'
import { addUserToGroup, createGroup, createGroupFolder, PERMISSION_DELETE, PERMISSION_READ, PERMISSION_WRITE } from '../groupfoldersUtils'
import { navigateToFolder } from '../files/filesUtils'

describe('Versions expiration', () => {
	let randomGroupName: string
	let randomGroupFolderName: string
	let randomFileName: string
	let randomFilePath: string
	let user1: User

	beforeEach(() => {
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

	it('Expire all versions', () => {
		cy.runOccCommand('config:system:set versions_retention_obligation --value "0, 0"')
		cy.runOccCommand('versions:expire')
		cy.runOccCommand('config:system:set versions_retention_obligation --value auto')
		cy.visit('/apps/files')
		openVersionsPanel(randomFileName)

		cy.get('#tab-version_vue').within(() => {
			cy.get('[data-files-versions-version]').should('have.length', 1)
			cy.get('[data-files-versions-version]').eq(0).contains('Current version')
		})

		assertVersionContent(0, 'v3')
	})

	it('Expire versions v2', () => {
		nameVersion(2, 'v1')

		cy.runOccCommand('config:system:set versions_retention_obligation --value "0, 0"')
		cy.runOccCommand('versions:expire')
		cy.runOccCommand('config:system:set versions_retention_obligation --value auto')
		cy.visit('/apps/files')
		openVersionsPanel(randomFileName)

		cy.get('#tab-version_vue').within(() => {
			cy.get('[data-files-versions-version]').should('have.length', 2)
			cy.get('[data-files-versions-version]').eq(0).contains('Current version')
			cy.get('[data-files-versions-version]').eq(1).contains('v1')
		})

		assertVersionContent(0, 'v3')
		assertVersionContent(1, 'v1')
	})
})
