/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '../support/fixtures/groupfolders.ts'
import { FilesListPage } from '../support/sections/FilesListPage.ts'
import { UnifiedSearchPage } from '../support/sections/UnifiedSearchPage.ts'
import { mkdir, uploadContent } from '../support/utils/dav.ts'
import { setAclPermissions } from '../support/utils/groupfolders.ts'

/**
 * Name of the i-th seeded file. Zero padded so no name is part of another one,
 * as results are matched by their text.
 *
 * @param index - Index of the file
 */
function fileName(index: number): string {
	return `searchable-${String(index).padStart(2, '0')}.txt`
}

test('unified search only finds team folder files the user may read', async ({ createUser, openSession, createTeamFolder }) => {
	const [manager, reader, restricted] = [await createUser(), await createUser(), await createUser()]
	const folder = await createTeamFolder({ members: [manager, reader, restricted], aclManagers: [manager] })

	// twelve files alternating between the two subfolders, with increasing
	// modification times so the newest file is listed first
	const managerPage = await openSession(manager)
	await mkdir(managerPage.request, manager, `${folder.mountPoint}/subfolder1`)
	await mkdir(managerPage.request, manager, `${folder.mountPoint}/subfolder2`)
	const now = Math.floor(Date.now() / 1000)
	for (let i = 0; i < 12; i++) {
		const subfolder = i % 2 === 0 ? 'subfolder1' : 'subfolder2'
		await uploadContent(managerPage.request, manager, `${folder.mountPoint}/${subfolder}/${fileName(i)}`, String(i), now + i)
	}

	await setAclPermissions(folder.id, '/subfolder1', reader, ['+read'])
	await setAclPermissions(folder.id, '/subfolder1', restricted, ['+read'])
	await setAclPermissions(folder.id, '/subfolder2', reader, ['+read'])
	await setAclPermissions(folder.id, '/subfolder2', restricted, ['-read'])

	await test.step('a user with access to both subfolders finds files of both', async () => {
		const page = await openSession(reader)
		await new FilesListPage(page).open()
		const search = new UnifiedSearchPage(page)
		await search.search('searchable')
		await search.showMoreFrom('Files')

		for (const i of [11, 10, 9, 8, 7]) {
			const subfolder = i % 2 === 0 ? 'subfolder1' : 'subfolder2'
			await expect(search.result('Files', fileName(i))).toContainText(`${folder.mountPoint}/${subfolder}`)
		}
		await expect(search.loadMoreButton()).toBeEnabled()
	})

	await test.step('a user without access to a subfolder only finds files of the other one', async () => {
		const page = await openSession(restricted)
		await new FilesListPage(page).open()
		const search = new UnifiedSearchPage(page)
		await search.search('searchable')
		await search.showMoreFrom('Files')

		for (const i of [10, 8, 6, 4, 2]) {
			await expect(search.result('Files', fileName(i))).toContainText(`${folder.mountPoint}/subfolder1`)
		}
		await expect(search.result('Files', fileName(11))).toHaveCount(0)
		await expect(search.result('Files', fileName(9))).toHaveCount(0)
		await expect(search.loadMoreButton()).toBeEnabled()
	})
})
