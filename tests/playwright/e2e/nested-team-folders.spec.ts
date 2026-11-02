/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '../support/fixtures/groupfolders.ts'

test('team folders mounted inside other team folders are listed', async ({ user, teamFolder, createTeamFolder, filesList }) => {
	for (const path of ['folder1', 'folder1/subfolder1', 'folder1/subfolder2', 'folder2', 'folder3']) {
		await createTeamFolder({ members: [user], mountPoint: `${teamFolder.mountPoint}/${path}` })
	}

	await filesList.open()
	await filesList.openFolderOfRow(filesList.getRowForFile(teamFolder.mountPoint))
	await expect(filesList.getRowForFile('folder1')).toBeVisible()
	await expect(filesList.getRowForFile('folder2')).toBeVisible()
	await expect(filesList.getRowForFile('folder3')).toBeVisible()

	await filesList.openFolderOfRow(filesList.getRowForFile('folder1'))
	await expect(filesList.getRowForFile('subfolder1')).toBeVisible()
	await expect(filesList.getRowForFile('subfolder2')).toBeVisible()
})
