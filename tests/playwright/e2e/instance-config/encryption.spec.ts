/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '../../support/fixtures/groupfolders.ts'
import { getFileContent, uploadContent } from '../../support/utils/dav.ts'
import { occ, occCleanup } from '../../support/utils/occ.ts'

const CONTENT = 'Content of the file'

/**
 * Configure which storages are encrypted.
 *
 * @param storages - The storages to encrypt
 * @param storages.home - Whether to encrypt the home storage of the users
 * @param storages.teamFolders - Whether to encrypt team folders
 */
async function encryptStorages({ home, teamFolders }: { home: boolean, teamFolders: boolean }): Promise<void> {
	if (home) {
		// encrypted by default
		await occ(['config:app:delete', 'encryption', 'encryptHomeStorage'])
	} else {
		await occ(['config:app:set', 'encryption', 'encryptHomeStorage', '--value=false', '--type=boolean'])
	}

	if (teamFolders) {
		await occ(['config:app:set', 'groupfolders', 'enable_encryption', '--value=true'])
	} else {
		await occ(['config:app:delete', 'groupfolders', 'enable_encryption'])
	}
}

const CASES = [
	{ home: true, teamFolders: true },
	{ home: true, teamFolders: false },
	{ home: false, teamFolders: true },
]

/**
 * Describe whether a storage is encrypted.
 *
 * @param encrypted - Whether the storage is encrypted
 */
function encryptedOrNot(encrypted: boolean): string {
	return encrypted ? 'encrypted' : 'unencrypted'
}

test.describe('Moving files between team folders and the home storage with server side encryption', () => {
	test.beforeAll(async () => {
		await occ(['app:enable', 'encryption'])
		await occ(['encryption:enable'])
	})

	test.afterAll(async () => {
		await occCleanup(['config:app:delete', 'groupfolders', 'enable_encryption'])
		await occCleanup(['config:app:delete', 'encryption', 'encryptHomeStorage'])
		await occCleanup(['encryption:disable'])
		await occCleanup(['app:disable', 'encryption'])
	})

	for (const { home, teamFolders } of CASES) {
		test(`from the ${encryptedOrNot(home)} home storage into an ${encryptedOrNot(teamFolders)} team folder`, async ({ page, user, teamFolder, filesList, copyMoveDialog }) => {
			await encryptStorages({ home, teamFolders })
			await uploadContent(page.request, user, 'file1.txt', CONTENT)

			await filesList.open()
			await filesList.triggerActionForFile('file1.txt', 'move-copy')
			await copyMoveDialog.moveTo(teamFolder.mountPoint)

			await filesList.openFolder(`/${teamFolder.mountPoint}`)
			await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
			expect(await getFileContent(page.request, user, `${teamFolder.mountPoint}/file1.txt`)).toBe(CONTENT)
		})
	}

	for (const { home, teamFolders } of CASES) {
		test(`from an ${encryptedOrNot(teamFolders)} team folder into the ${encryptedOrNot(home)} home storage`, async ({ page, user, teamFolder, filesList, copyMoveDialog }) => {
			await encryptStorages({ home, teamFolders })
			await uploadContent(page.request, user, `${teamFolder.mountPoint}/file1.txt`, CONTENT)

			await filesList.openFolder(`/${teamFolder.mountPoint}`)
			await filesList.triggerActionForFile('file1.txt', 'move-copy')
			await copyMoveDialog.goToAllFiles()
			await copyMoveDialog.moveToRoot()

			await filesList.open()
			await expect(filesList.getRowForFile('file1.txt')).toBeVisible()
			expect(await getFileContent(page.request, user, 'file1.txt')).toBe(CONTENT)
		})
	}
})
