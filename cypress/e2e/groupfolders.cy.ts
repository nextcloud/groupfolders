/**
 * @copyright Copyright (c) 2023 Louis Chmn <louis@chmn.me>
 *
 * @author Louis Chmn <louis@chmn.me>
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
	addACLManagerUser,
	addUserToGroup,
	createGroup,
	createGroupFolder,
	deleteGroupFolder,
	enableACLPermissions,
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

	before(() => {
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
							})
					})
			})
	})

	after(() => {
		if (groupFolderId) {
			deleteGroupFolder(groupFolderId)
		}
	})

	it('Configure ACL manager', () => {
		enableACLPermissions(groupFolderId)
		addACLManagerUser(groupFolderId,managerUser.userId)
	})

	it('Visit the group folder as user1', () => {
		cy.login(user1)
		cy.visit('/apps/files')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${groupFolderName}"]`).should('be.visible')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${groupFolderName}"]`).click()
	})

	it('Create two subfolders and a file as manager', () => {
		cy.login(managerUser)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1`)
		cy.mkdir(managerUser, `/${groupFolderName}/subfolder1/subfolder2`)
		cy.uploadContent(managerUser, new Blob(['Content of the file']), 'text/plain', `/${groupFolderName}/subfolder1/subfolder2/file.txt`)
	})

	it('Set ACL permissions', () => {
		setACLPermissions(groupFolderId, '/subfolder1', [`+${PERMISSION_READ}`,`-${PERMISSION_WRITE}`], undefined, user1.userId)
		setACLPermissions(groupFolderId, '/subfolder1', [`-${PERMISSION_READ}`], undefined, user2.userId)
	})

	it('User1 has access', () => {
		cy.login(user1)
		cy.visit('/apps/files')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${groupFolderName}"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"]`).should('be.visible')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder2"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="file.txt"]`).should('be.visible')
	})

	it('User2 has no access', () => {
		cy.login(user2)
		cy.visit('/apps/files')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${groupFolderName}"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"]`).should('not.exist')
	})

	it('Delete file.txt', () => {
		cy.login(managerUser)
		cy.visit('/apps/files')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${groupFolderName}"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder2"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="file.txt"] [data-cy-files-list-row-actions]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).scrollIntoView()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="delete"]`).click()
	})

	it('User1 sees it in trash', () => {
		cy.login(user1)
		cy.visit('/apps/files/trashbin')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="file.txt.d"]`).should('be.visible')
	})

	it('User2 does not', () => {
		cy.login(user2)
		cy.visit('/apps/files/trashbin')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="file.txt.d"]`).should('not.exist')
	})

	it('Rename subfolder2', () => {
		cy.login(managerUser)
		cy.visit('/apps/files')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="${groupFolderName}"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder1"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder2"] [data-cy-files-list-row-actions]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="rename"]`).scrollIntoView()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-action="rename"]`).click()
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder2"] [class="files-list__row-rename"] [class="input-field__input"]`).type('subfolder2renamed{enter}')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name="subfolder2renamed"]`).should('be.visible')
	})

	it('User1 still sees it in trash', () => {
		cy.login(user1)
		cy.visit('/apps/files/trashbin')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="file.txt.d"]`).should('be.visible')
	})

	it('User2 still does not', () => {
		cy.login(user2)
		cy.visit('/apps/files/trashbin')
		cy.get(`[data-cy-files-list] [data-cy-files-list-row-name^="file.txt.d"]`).should('not.exist')
	})
})
