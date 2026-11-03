/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FilesListPage } from '../support/sections/FilesListPage.ts'

import { expect, test } from '../support/fixtures/groupfolders.ts'
import { uploadContent } from '../support/utils/dav.ts'

/**
 * Open the only team folder listed in the team folders view.
 *
 * The view lists the folders by their team folder id rather than by their
 * mount point, so the row is not looked up by its name.
 *
 * @param filesList - The files list
 */
async function openTeamFolderFromView(filesList: FilesListPage): Promise<void> {
	await expect(filesList.getRows()).toHaveCount(1)
	await filesList.openFolderOfRow(filesList.getRows().first())
}

// Regression tests for https://github.com/nextcloud/groupfolders/issues/4499
test.describe('Team folders view navigation', () => {
	test.beforeEach(async ({ page, user, teamFolder }) => {
		await uploadContent(page.request, user, `${teamFolder.mountPoint}/file1.txt`, 'hello')
	})

	test('opening a team folder navigates to its file id and lists its contents', async ({ page, teamFolder, filesList }) => {
		await filesList.open('groupfolders')
		await openTeamFolderFromView(filesList)

		await expect(page).toHaveURL((url) => /\/apps\/files\/files\/\d+$/.test(url.pathname)
			&& url.searchParams.get('dir') === `/${teamFolder.mountPoint}`)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})

	test('a team folder can be opened again after returning to the view', async ({ page, filesList }) => {
		await filesList.open('groupfolders')
		await openTeamFolderFromView(filesList)
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()

		await filesList.open('groupfolders')
		await openTeamFolderFromView(filesList)

		await expect(page).toHaveURL((url) => /\/apps\/files\/files\/\d+$/.test(url.pathname))
		await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
	})
})
