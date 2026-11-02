/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '../../support/fixtures/versions.ts'
import { occ } from '../../support/utils/occ.ts'

/**
 * Expire the versions of all team folders with a retention that only keeps
 * the current and the named versions. The retention is a system config, so it
 * is reset to its default right away.
 */
async function expireVersions(): Promise<void> {
	await occ(['config:system:set', 'versions_retention_obligation', '--value', '0, 0'])
	try {
		await occ(['groupfolders:expire'])
	} finally {
		await occ(['config:system:set', 'versions_retention_obligation', '--value', 'auto'])
	}
}

test.describe('Expiring versions of team folder files', () => {
	test('expires all but the current version', async ({ teamFolder, versionedFile, filesList, versionsTab }) => {
		await expireVersions()

		await filesList.openFolder(`/${teamFolder.mountPoint}`)
		await versionsTab.openFor(filesList, versionedFile)
		await expect(versionsTab.versions()).toHaveCount(1)
		await expect(versionsTab.version(0)).toContainText('Current version')
		expect(await versionsTab.getVersionContent(0)).toBe('v3')
	})

	test('keeps named versions', async ({ teamFolder, versionedFile, filesList, versionsTab }) => {
		await versionsTab.nameVersion(2, 'v1')
		await expireVersions()

		await filesList.openFolder(`/${teamFolder.mountPoint}`)
		await versionsTab.openFor(filesList, versionedFile)
		await expect(versionsTab.versions()).toHaveCount(2)
		await expect(versionsTab.version(0)).toContainText('Current version')
		await expect(versionsTab.version(1)).toContainText('v1')
		expect(await versionsTab.getVersionContent(0)).toBe('v3')
		expect(await versionsTab.getVersionContent(1)).toBe('v1')
	})
})
