/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { BrowserContext, Page } from '@playwright/test'
import type { TeamFolderPermission } from '../utils/groupfolders.ts'

import { createRandomUser, login } from '@nextcloud/e2e-test-server/playwright'
import { test as base } from '@playwright/test'
import { CopyMoveDialogPage } from '../sections/CopyMoveDialogPage.ts'
import { FilesListPage } from '../sections/FilesListPage.ts'
import { FilesSidebarPage } from '../sections/FilesSidebarPage.ts'
import { VersionsTab } from '../sections/VersionsTab.ts'
import {
	addAclManager,
	createGroup,
	createTeamFolder,
	deleteGroup,
	deleteTeamFolder,
	enableAdvancedPermissions,
	randomName,
} from '../utils/groupfolders.ts'
import { occCleanup } from '../utils/occ.ts'
import { retryWithBackoff } from '../utils/retry.ts'

export interface TeamFolder {
	id: number
	/** Mount point of the folder, i.e. its path relative to the root of the members' files */
	mountPoint: string
	/** The group granting the members access */
	groupId: string
}

export interface TeamFolderOptions {
	/** Users with access to the folder */
	members: User[]
	/** Permissions of the members, read, write and delete by default */
	permissions?: TeamFolderPermission[]
	/** Mount point of the folder, a random name by default */
	mountPoint?: string
	/** Enable advanced permissions and let these users manage them */
	aclManagers?: User[]
}

interface GroupfoldersFixtures {
	/** Create a user, deleted again after the test */
	createUser: () => Promise<User>
	/**
	 * Open a page logged in as a user, in a browser context of its own so
	 * several users can be driven side by side
	 */
	openSession: (user: User) => Promise<Page>
	/** Create a team folder for a new group of the given members, deleted again after the test */
	createTeamFolder: (options: TeamFolderOptions) => Promise<TeamFolder>

	/** A user that `page` is logged in as */
	user: User
	/** A team folder `user` has full access to */
	teamFolder: TeamFolder

	filesList: FilesListPage
	filesSidebar: FilesSidebarPage
	copyMoveDialog: CopyMoveDialogPage
	versionsTab: VersionsTab
}

export const test = base.extend<GroupfoldersFixtures>({
	// eslint-disable-next-line no-empty-pattern
	createUser: async ({}, use) => {
		const users: User[] = []
		await use(async () => {
			const user = await retryWithBackoff('Creating a random user', () => createRandomUser())
			users.push(user)
			return user
		})
		for (const user of users) {
			await occCleanup(['user:delete', user.userId])
		}
	},

	openSession: async ({ browser }, use) => {
		const contexts: BrowserContext[] = []
		await use(async (user) => {
			const context = await browser.newContext()
			contexts.push(context)
			const page = await context.newPage()
			await retryWithBackoff(`Logging in as ${user.userId}`, () => login(page.request, user))
			return page
		})
		for (const context of contexts) {
			await context.close()
		}
	},

	// eslint-disable-next-line no-empty-pattern
	createTeamFolder: async ({}, use) => {
		const folders: TeamFolder[] = []
		await use(async ({ members, permissions = ['read', 'write', 'delete'], mountPoint = `team-folder-${randomName()}`, aclManagers = [] }) => {
			const groupId = `group-${randomName()}`
			await createGroup(groupId, members)
			const id = await createTeamFolder(mountPoint, groupId, permissions)
			const folder = { id, mountPoint, groupId }
			folders.push(folder)

			if (aclManagers.length > 0) {
				await enableAdvancedPermissions(id)
				for (const manager of aclManagers) {
					await addAclManager(id, manager)
				}
			}
			return folder
		})
		for (const { id, groupId } of folders) {
			await deleteTeamFolder(id)
			await deleteGroup(groupId)
		}
	},

	user: async ({ createUser }, use) => {
		await use(await createUser())
	},

	teamFolder: async ({ createTeamFolder, user }, use) => {
		await use(await createTeamFolder({ members: [user], permissions: ['read', 'write', 'delete', 'share'] }))
	},

	page: async ({ openSession, user }, use) => {
		await use(await openSession(user))
	},

	filesList: async ({ page }, use) => {
		await use(new FilesListPage(page))
	},

	filesSidebar: async ({ page }, use) => {
		await use(new FilesSidebarPage(page))
	},

	copyMoveDialog: async ({ page }, use) => {
		await use(new CopyMoveDialogPage(page))
	},

	versionsTab: async ({ page }, use) => {
		await use(new VersionsTab(page))
	},
})

export { expect } from '@playwright/test'
