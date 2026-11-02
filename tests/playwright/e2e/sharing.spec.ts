/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { TeamFolder } from '../support/fixtures/groupfolders.ts'

import { test as base, expect } from '../support/fixtures/groupfolders.ts'
import { mkdir, uploadContent } from '../support/utils/dav.ts'
import { createUserShare, waitForShare } from '../support/utils/sharing.ts'

interface SharingFixtures {
	/** A team folder of another user, shared with `user` and containing `file1.txt` */
	sharedFolder: TeamFolder
}

const test = base.extend<SharingFixtures>({
	// `teamFolder` is the recipient's own team folder
	teamFolder: async ({ createTeamFolder, user }, use) => {
		await use(await createTeamFolder({ members: [user], permissions: ['read', 'write', 'share', 'delete'] }))
	},

	sharedFolder: async ({ createUser, openSession, createTeamFolder, page, user }, use) => {
		const owner = await createUser()
		const folder = await createTeamFolder({ members: [owner], permissions: ['read', 'write', 'share', 'delete'] })

		const ownerPage = await openSession(owner)
		await uploadContent(ownerPage.request, owner, `${folder.mountPoint}/file1.txt`, 'Content of the file')
		await createUserShare(ownerPage.request, `/${folder.mountPoint}`, user)
		await waitForShare(page.request, user, '', folder.mountPoint)

		await use(folder)
	},
})

test.describe('Team folders shared with a user', () => {
	test('copy the shared team folder into a folder', async ({ page, user, sharedFolder, filesList, copyMoveDialog }) => {
		await mkdir(page.request, user, 'Target')

		await filesList.open()
		await filesList.triggerActionForFile(sharedFolder.mountPoint, 'move-copy')
		await copyMoveDialog.copyTo('Target')

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
		await filesList.openFolder(`/Target/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})

	test('copy the shared team folder into another team folder', async ({ teamFolder, sharedFolder, filesList, copyMoveDialog }) => {
		await filesList.open()
		await filesList.triggerActionForFile(sharedFolder.mountPoint, 'move-copy')
		await copyMoveDialog.copyTo(teamFolder.mountPoint)

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
		await filesList.openFolder(`/${teamFolder.mountPoint}/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})

	test('copy a file of the shared team folder into a folder', async ({ page, user, sharedFolder, filesList, copyMoveDialog }) => {
		await mkdir(page.request, user, 'Target')

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await filesList.triggerActionForFile('file1.txt', 'move-copy')
		await copyMoveDialog.goToAllFiles()
		await copyMoveDialog.copyTo('Target')

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
		await filesList.openFolder('/Target')
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})

	test('copy a file of the shared team folder into another team folder', async ({ teamFolder, sharedFolder, filesList, copyMoveDialog }) => {
		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await filesList.triggerActionForFile('file1.txt', 'move-copy')
		await copyMoveDialog.goToAllFiles()
		await copyMoveDialog.copyTo(teamFolder.mountPoint)

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
		await filesList.openFolder(`/${teamFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})

	test('move the shared team folder into a folder', async ({ page, user, sharedFolder, filesList, copyMoveDialog }) => {
		await mkdir(page.request, user, 'Target')

		await filesList.open()
		await filesList.triggerActionForFile(sharedFolder.mountPoint, 'move-copy')
		await copyMoveDialog.moveTo('Target')

		await filesList.open()
		await expect(filesList.getRowForFile('Target')).toBeVisible()
		await expect(filesList.getRowForFile(sharedFolder.mountPoint)).toHaveCount(0)
		await filesList.openFolder(`/Target/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})

	test('move a file of the shared team folder into a folder', async ({ page, user, sharedFolder, filesList, copyMoveDialog }) => {
		await mkdir(page.request, user, 'Target')

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await filesList.triggerActionForFile('file1.txt', 'move-copy')
		await copyMoveDialog.goToAllFiles()
		await copyMoveDialog.moveTo('Target')

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toHaveCount(0)
		await filesList.openFolder('/Target')
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})

	test('move a file of the shared team folder into another team folder', async ({ teamFolder, sharedFolder, filesList, copyMoveDialog }) => {
		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await filesList.triggerActionForFile('file1.txt', 'move-copy')
		await copyMoveDialog.goToAllFiles()
		await copyMoveDialog.moveTo(teamFolder.mountPoint)

		await filesList.openFolder(`/${sharedFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toHaveCount(0)
		await filesList.openFolder(`/${teamFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})
})
