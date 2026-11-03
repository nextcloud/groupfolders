/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'
import type { TeamFolder } from '../support/fixtures/groupfolders.ts'

import { test as base, expect } from '../support/fixtures/groupfolders.ts'
import { TrashbinListPage } from '../support/sections/TrashbinListPage.ts'
import { mkdir, rm, uploadContent } from '../support/utils/dav.ts'
import { setAclPermissions } from '../support/utils/groupfolders.ts'

interface Member {
	user: User
	page: Page
	files: TrashbinListPage
}

interface AclFixtures {
	/** Manages the advanced permissions of the folder and seeds its contents */
	manager: Member
	/** May read but not write `/subfolder1` */
	reader: Member
	/** May not read `/subfolder1` */
	denied: Member
	folder: TeamFolder
}

const test = base.extend<AclFixtures>({
	manager: async ({ createUser, openSession }, use) => {
		const user = await createUser()
		const page = await openSession(user)
		await use({ user, page, files: new TrashbinListPage(page) })
	},
	reader: async ({ createUser, openSession }, use) => {
		const user = await createUser()
		const page = await openSession(user)
		await use({ user, page, files: new TrashbinListPage(page) })
	},
	denied: async ({ createUser, openSession }, use) => {
		const user = await createUser()
		const page = await openSession(user)
		await use({ user, page, files: new TrashbinListPage(page) })
	},
	folder: async ({ createTeamFolder, manager, reader, denied }, use) => {
		await use(await createTeamFolder({
			members: [manager.user, reader.user, denied.user],
			aclManagers: [manager.user],
		}))
	},
})

/**
 * Seed `/subfolder1/file1.txt` in the folder, and `/subfolder1/subfolder2/file2.txt`
 * if `nested` is set, as the manager.
 *
 * @param manager - The manager of the folder
 * @param folder - The team folder
 * @param nested - Whether to also create the nested folder
 */
async function seedSubfolder(manager: Member, folder: TeamFolder, nested = false): Promise<void> {
	const request = manager.page.request
	await mkdir(request, manager.user, `${folder.mountPoint}/subfolder1`)
	await uploadContent(request, manager.user, `${folder.mountPoint}/subfolder1/file1.txt`, 'Content of the file')
	if (nested) {
		await mkdir(request, manager.user, `${folder.mountPoint}/subfolder1/subfolder2`)
		await uploadContent(request, manager.user, `${folder.mountPoint}/subfolder1/subfolder2/file2.txt`, 'Content of the file')
	}
}

/**
 * Let `reader` read but not write `/subfolder1` and hide it from `denied`.
 *
 * @param folder - The team folder
 * @param reader - The member allowed to read
 * @param denied - The member denied to read
 */
async function restrictSubfolder(folder: TeamFolder, reader: Member, denied: Member): Promise<void> {
	await setAclPermissions(folder.id, '/subfolder1', reader.user, ['+read', '-write'])
	await setAclPermissions(folder.id, '/subfolder1', denied.user, ['-read'])
}

test.describe('Team folder advanced permissions and the trash bin', () => {
	test('trashed and restored nodes keep their advanced permissions', async ({ manager, reader, denied, folder }) => {
		await seedSubfolder(manager, folder, true)
		await restrictSubfolder(folder, reader, denied)

		await reader.files.openFolder(`/${folder.mountPoint}/subfolder1`)
		await expect(reader.files.getRowForFile('file1.txt')).toBeVisible()
		await reader.files.openFolderOfRow(reader.files.getRowForFile('subfolder2'))
		await expect(reader.files.getRowForFile('file2.txt')).toBeVisible()

		await denied.files.openFolder(`/${folder.mountPoint}`)
		await expect(denied.files.getRowForFile('subfolder1')).toHaveCount(0)

		await rm(manager.page.request, manager.user, `${folder.mountPoint}/subfolder1/file1.txt`)
		await rm(manager.page.request, manager.user, `${folder.mountPoint}/subfolder1/subfolder2`)

		// the reader sees the trashed nodes, including the contents of the trashed folder
		await reader.files.openTrashbin()
		await expect(reader.files.getTrashedRow('file1.txt')).toBeVisible()
		await reader.files.openTrashedFolder('subfolder2')
		await expect(reader.files.getRowForFile('file2.txt')).toBeVisible()

		await denied.files.openTrashbin()
		await expect(denied.files.getTrashedRow('file1.txt')).toHaveCount(0)
		await expect(denied.files.getTrashedRow('subfolder2')).toHaveCount(0)

		await manager.files.openTrashbin()
		await manager.files.restore('file1.txt')
		await manager.files.restore('subfolder2')

		await reader.files.openFolder(`/${folder.mountPoint}/subfolder1`)
		await expect(reader.files.getRowForFile('file1.txt')).toBeVisible()
		await reader.files.openFolderOfRow(reader.files.getRowForFile('subfolder2'))
		await expect(reader.files.getRowForFile('file2.txt')).toBeVisible()

		await denied.files.openFolder(`/${folder.mountPoint}`)
		await expect(denied.files.getRowForFile('subfolder1')).toHaveCount(0)
	})

	test('a trashed folder keeps the advanced permissions set on itself', async ({ manager, reader, denied, folder }) => {
		await seedSubfolder(manager, folder)
		await restrictSubfolder(folder, reader, denied)

		await rm(manager.page.request, manager.user, `${folder.mountPoint}/subfolder1`)

		await reader.files.openTrashbin()
		await reader.files.openTrashedFolder('subfolder1')
		await expect(reader.files.getRowForFile('file1.txt')).toBeVisible()

		await denied.files.openTrashbin()
		await expect(denied.files.getTrashedRow('subfolder1')).toHaveCount(0)
	})

	test('a trashed node is restored into its renamed parent', async ({ manager, folder }) => {
		await seedSubfolder(manager, folder)
		await rm(manager.page.request, manager.user, `${folder.mountPoint}/subfolder1/file1.txt`)

		await manager.files.openFolder(`/${folder.mountPoint}`)
		await manager.files.rename('subfolder1', 'subfolder1renamed')

		await manager.files.openTrashbin()
		await manager.files.restore('file1.txt')

		await manager.files.openFolder(`/${folder.mountPoint}`)
		await expect(manager.files.getRowForFile('subfolder1renamed')).toBeVisible()
		await expect(manager.files.getRowForFile('file1.txt')).toHaveCount(0)
		await manager.files.openFolderOfRow(manager.files.getRowForFile('subfolder1renamed'))
		await expect(manager.files.getRowForFile('file1.txt')).toBeVisible()
	})

	test('a trashed node keeps the advanced permissions of its renamed parent', async ({ manager, reader, denied, folder }) => {
		await seedSubfolder(manager, folder)
		await restrictSubfolder(folder, reader, denied)
		await rm(manager.page.request, manager.user, `${folder.mountPoint}/subfolder1/file1.txt`)

		await manager.files.openFolder(`/${folder.mountPoint}`)
		await manager.files.rename('subfolder1', 'subfolder1renamed')

		await reader.files.openTrashbin()
		await expect(reader.files.getTrashedRow('file1.txt')).toBeVisible()

		await denied.files.openTrashbin()
		await expect(denied.files.getTrashedRow('file1.txt')).toHaveCount(0)
	})

	test('trashed nodes with advanced permissions can be deleted permanently', async ({ manager, reader, denied, folder }) => {
		await seedSubfolder(manager, folder, true)
		await restrictSubfolder(folder, reader, denied)
		await rm(manager.page.request, manager.user, `${folder.mountPoint}/subfolder1/file1.txt`)
		await rm(manager.page.request, manager.user, `${folder.mountPoint}/subfolder1/subfolder2`)

		await manager.files.openTrashbin()
		await manager.files.deletePermanently('file1.txt')
		await manager.files.deletePermanently('subfolder2')

		// gone for good, not only from the list the browser still had
		await manager.files.openTrashbin()
		await expect(manager.files.getTrashedRow('file1.txt')).toHaveCount(0)
		await expect(manager.files.getTrashedRow('subfolder2')).toHaveCount(0)

		await reader.files.openTrashbin()
		await expect(reader.files.getTrashedRow('file1.txt')).toHaveCount(0)
		await expect(reader.files.getTrashedRow('subfolder2')).toHaveCount(0)
	})
})
