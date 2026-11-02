/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '../../support/fixtures/versions.ts'

test.describe('Versions of team folder files', () => {
	test('lists the versions of a file', async ({ versionsTab }) => {
		await expect(versionsTab.versions()).toHaveCount(3)
		await expect(versionsTab.version(0)).toContainText('Current version')
		await expect(versionsTab.version(2)).toContainText('Initial version')
	})

	test('downloads the versions', async ({ versionsTab }) => {
		expect(await versionsTab.getVersionContent(0)).toBe('v3')
		expect(await versionsTab.getVersionContent(1)).toBe('v2')
		expect(await versionsTab.getVersionContent(2)).toBe('v1')
	})

	test('names the versions', async ({ versionsTab }) => {
		await versionsTab.nameVersion(2, 'v1')
		await expect(versionsTab.version(2)).toContainText('v1')
		await expect(versionsTab.version(2)).not.toContainText('Initial version')

		await versionsTab.nameVersion(1, 'v2')
		await expect(versionsTab.version(1)).toContainText('v2')

		await versionsTab.nameVersion(0, 'v3')
		await expect(versionsTab.version(0)).toContainText('v3 (Current version)')
	})

	test('deletes a version', async ({ teamFolder, versionedFile, filesList, versionsTab }) => {
		await versionsTab.delete(2)
		await expect(versionsTab.versions()).toHaveCount(2)

		// still gone once listed anew
		await filesList.openFolder(`/${teamFolder.mountPoint}`)
		await versionsTab.openFor(filesList, versionedFile)
		await expect(versionsTab.versions()).toHaveCount(2)
		expect(await versionsTab.getVersionContent(1)).toBe('v2')
	})

	test('the current version cannot be restored', async ({ versionsTab }) => {
		await versionsTab.expectActionMissing(0, 'restore')
	})

	test('restores the initial version', async ({ versionsTab }) => {
		await versionsTab.restore(2)

		await expect(versionsTab.versions()).toHaveCount(3)
		await expect(versionsTab.version(0)).toContainText('Current version')
		await expect(versionsTab.version(2)).not.toContainText('Initial version')

		expect(await versionsTab.getVersionContent(0)).toBe('v1')
		expect(await versionsTab.getVersionContent(1)).toBe('v3')
		expect(await versionsTab.getVersionContent(2)).toBe('v2')
	})
})
