/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FilesListPage } from '../../support/sections/FilesListPage.ts'
import type { VersionsTab } from '../../support/sections/VersionsTab.ts'

import { expect, test } from '../../support/fixtures/groupfolders.ts'
import { mkdir } from '../../support/utils/dav.ts'
import { seedThreeVersions } from '../../support/utils/versions.ts'

const FILE_NAME = 'versioned.txt'

/**
 * Open the versions of a file listed anew and assert they are the three
 * seeded versions, the initial one named "v1".
 *
 * @param filesList - The files list
 * @param versionsTab - The versions tab
 * @param path - Path of the file relative to the user's root
 */
async function expectVersions(filesList: FilesListPage, versionsTab: VersionsTab, path: string): Promise<void> {
	const segments = path.split('/')
	await filesList.openFolder(`/${segments.slice(0, -1).join('/')}`)
	await versionsTab.openFor(filesList, segments.at(-1)!)

	await expect(versionsTab.versions()).toHaveCount(3)
	await expect(versionsTab.version(2)).toContainText('v1')
	expect(await versionsTab.getVersionContent(0)).toBe('v3')
	expect(await versionsTab.getVersionContent(1)).toBe('v2')
	expect(await versionsTab.getVersionContent(2)).toBe('v1')
}

test.describe('Versions moved or copied between a team folder and the home folder', () => {
	test.beforeEach(async ({ page, user, teamFolder, filesList, filesSidebar, versionsTab }) => {
		await seedThreeVersions(page.request, user, `${teamFolder.mountPoint}/${FILE_NAME}`)

		await filesList.openFolder(`/${teamFolder.mountPoint}`)
		await versionsTab.openFor(filesList, FILE_NAME)
		await versionsTab.nameVersion(2, 'v1')
		await filesSidebar.close()
	})

	test('moving a file out of the team folder and back keeps its versions', async ({ teamFolder, filesList, versionsTab, filesSidebar, copyMoveDialog }) => {
		await filesList.triggerActionForFile(FILE_NAME, 'move-copy')
		await copyMoveDialog.goToAllFiles()
		await copyMoveDialog.moveToRoot()
		await expectVersions(filesList, versionsTab, FILE_NAME)
		await filesSidebar.close()

		await filesList.triggerActionForFile(FILE_NAME, 'move-copy')
		await copyMoveDialog.moveTo(teamFolder.mountPoint)
		await expectVersions(filesList, versionsTab, `${teamFolder.mountPoint}/${FILE_NAME}`)
	})

	test('copying a file out of the team folder and back copies its versions', async ({ teamFolder, filesList, versionsTab, filesSidebar, copyMoveDialog }) => {
		await filesList.triggerActionForFile(FILE_NAME, 'move-copy')
		await copyMoveDialog.goToAllFiles()
		await copyMoveDialog.copyToRoot()
		await expectVersions(filesList, versionsTab, FILE_NAME)
		await filesSidebar.close()

		await filesList.triggerActionForFile(FILE_NAME, 'move-copy')
		await copyMoveDialog.copyTo(teamFolder.mountPoint)
		await expectVersions(filesList, versionsTab, `${teamFolder.mountPoint}/versioned (1).txt`)
	})

	test.describe('when the file is in a subfolder', () => {
		test.beforeEach(async ({ page, user, teamFolder, filesList, copyMoveDialog }) => {
			await mkdir(page.request, user, `${teamFolder.mountPoint}/subfolder`)
			await mkdir(page.request, user, `${teamFolder.mountPoint}/subfolder/nested`)

			await filesList.openFolder(`/${teamFolder.mountPoint}`)
			await filesList.triggerActionForFile(FILE_NAME, 'move-copy')
			await copyMoveDialog.moveTo('subfolder/nested')
		})

		test('moving the containing folder out of the team folder and back keeps the versions', async ({ teamFolder, filesList, versionsTab, filesSidebar, copyMoveDialog }) => {
			await filesList.openFolder(`/${teamFolder.mountPoint}`)
			await filesList.triggerActionForFile('subfolder', 'move-copy')
			await copyMoveDialog.goToAllFiles()
			await copyMoveDialog.moveToRoot()
			await expectVersions(filesList, versionsTab, `subfolder/nested/${FILE_NAME}`)
			await filesSidebar.close()

			await filesList.open()
			await filesList.triggerActionForFile('subfolder', 'move-copy')
			await copyMoveDialog.moveTo(teamFolder.mountPoint)
			await expectVersions(filesList, versionsTab, `${teamFolder.mountPoint}/subfolder/nested/${FILE_NAME}`)
		})

		test('copying the containing folder out of the team folder and back copies the versions', async ({ teamFolder, filesList, versionsTab, filesSidebar, copyMoveDialog }) => {
			test.fixme(true, 'The copy event from a team folder to the home storage does not contain the copied files yet')

			await filesList.openFolder(`/${teamFolder.mountPoint}`)
			await filesList.triggerActionForFile('subfolder', 'move-copy')
			await copyMoveDialog.goToAllFiles()
			await copyMoveDialog.copyToRoot()
			await expectVersions(filesList, versionsTab, `subfolder/nested/${FILE_NAME}`)
			await filesSidebar.close()

			await filesList.open()
			await filesList.triggerActionForFile('subfolder', 'move-copy')
			await copyMoveDialog.copyTo(teamFolder.mountPoint)
			await expectVersions(filesList, versionsTab, `${teamFolder.mountPoint}/subfolder (1)/nested/${FILE_NAME}`)
		})
	})
})
