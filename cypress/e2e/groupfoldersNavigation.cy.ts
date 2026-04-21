/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server/cypress'

import {
	PERMISSION_READ,
	PERMISSION_WRITE,
	addUserToGroup,
	createGroup,
	createGroupFolder,
	deleteGroupFolder,
	fileOrFolderExists,
} from './groupfoldersUtils.ts'
import { getRowForFile } from './files/filesUtils.ts'
import { randHash } from '../utils/index.js'

// Regression coverage for https://github.com/nextcloud/groupfolders/issues/4499:
// clicking a team folder from the Team folders view must navigate to the
// target route *with* the fileid segment (PR #4524) and render content — including
// on repeated navigation, which is where the stale-route / abort-race failures
// used to surface.
describe('Team folders view navigation', () => {
	let user: User
	let groupFolderId: string
	let groupName: string
	let groupFolderName: string

	beforeEach(() => {
		if (groupFolderId) {
			deleteGroupFolder(groupFolderId)
		}
		groupName = `test_group_${randHash()}`
		groupFolderName = `test_group_folder_${randHash()}`

		cy.createRandomUser().then(_user => {
			user = _user
			createGroup(groupName).then(() => {
				addUserToGroup(groupName, user.userId)
				createGroupFolder(groupFolderName, groupName, [PERMISSION_READ, PERMISSION_WRITE])
					.then(_id => {
						groupFolderId = _id
					})
			})
		})
	})

	it('clicking a team folder navigates to /apps/files/files/<fileid> with dir query and renders content', () => {
		cy.uploadContent(user, new Blob(['hello']), 'text/plain', `/${groupFolderName}/file1.txt`)

		cy.login(user)
		cy.visit('/apps/files/groupfolders')

		getRowForFile(groupFolderName).should('be.visible')

		cy.intercept({ method: 'PROPFIND', url: `**/dav/files/**/${groupFolderName}` }).as('propFindFolder')
		getRowForFile(groupFolderName)
			.find('[data-cy-files-list-row-name-link]')
			.click({ force: true })
		cy.wait('@propFindFolder')

		// PR 4524: route must include the fileid segment, not just /apps/files/files?dir=...
		cy.location('pathname').should('match', /\/apps\/files\/files\/\d+$/)
		cy.location('search').should('contain', `dir=%2F${groupFolderName}`)

		// If exec returns before navigation settles or lets a router rejection bubble,
		// the file list stays empty / shows "folder not found".
		fileOrFolderExists('file1.txt')
	})

	it('navigating back to the Team folders view and re-entering the same folder still works', () => {
		cy.uploadContent(user, new Blob(['hello']), 'text/plain', `/${groupFolderName}/file1.txt`)

		cy.login(user)
		cy.visit('/apps/files/groupfolders')
		getRowForFile(groupFolderName).should('be.visible')

		cy.intercept({ method: 'PROPFIND', url: `**/dav/files/**/${groupFolderName}` }).as('propFind1')
		getRowForFile(groupFolderName)
			.find('[data-cy-files-list-row-name-link]')
			.click({ force: true })
		cy.wait('@propFind1')
		fileOrFolderExists('file1.txt')

		cy.visit('/apps/files/groupfolders')
		getRowForFile(groupFolderName).should('be.visible')

		// Second click — the stale-router path from the bug report.
		cy.intercept({ method: 'PROPFIND', url: `**/dav/files/**/${groupFolderName}` }).as('propFind2')
		getRowForFile(groupFolderName)
			.find('[data-cy-files-list-row-name-link]')
			.click({ force: true })
		cy.wait('@propFind2')

		cy.location('pathname').should('match', /\/apps\/files\/files\/\d+$/)
		fileOrFolderExists('file1.txt')
	})
})
