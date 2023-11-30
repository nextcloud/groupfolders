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
	addUserToGroup,
	createGroup,
	createGroupFolder,
	deleteGroupFolder,
	PERMISSION_READ,
	PERMISSION_WRITE,
} from './groupfoldersUtils'

import { randHash } from '../utils'

import type { User } from '@nextcloud/cypress'

describe('Manage groupfolders', () => {
	let user: User
	let groupFolderId: string
	let groupName: string
	let groupFolderName: string

	before(() => {
		groupName = `test_group_${randHash()}`
		groupFolderName = `test_group_folder_${randHash()}`

		cy.createRandomUser()
			.then(_user => {
				user = _user
				cy.login(user)

				createGroup(groupName)
					.then(() => {
						addUserToGroup(groupName, user.userId)
						createGroupFolder(groupFolderName, groupName, [PERMISSION_READ, PERMISSION_WRITE])
							.then(_groupFolderId => {
								groupFolderId = _groupFolderId
								cy.visit('/apps/files')
							})
					})
			})
	})

	after(() => {
		if (groupFolderId) {
			deleteGroupFolder(groupFolderId)
		}
	})

	it('Visite the group folder', () => {
		return true
	})
})
