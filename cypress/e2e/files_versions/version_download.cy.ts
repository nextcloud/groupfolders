/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/cypress'

import { assertVersionContent, openVersionsPanel, uploadThreeVersions } from './filesVersionsUtils'
import { PERMISSION_DELETE, PERMISSION_READ, PERMISSION_WRITE, addUserToGroup, createGroup, createGroupFolder } from '../groupfoldersUtils'
import { navigateToFolder } from '../files/filesUtils'

describe('Versions download', () => {
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

	it('Download versions and assert their content', () => {
		assertVersionContent(0, 'v3')
		assertVersionContent(1, 'v2')
		assertVersionContent(2, 'v1')
	})
})
