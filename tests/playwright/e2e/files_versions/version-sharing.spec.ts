/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, test } from '../../support/fixtures/groupfolders.ts'
import { FilesListPage } from '../../support/sections/FilesListPage.ts'
import { VersionsTab } from '../../support/sections/VersionsTab.ts'
import { createUserShare, waitForShare } from '../../support/utils/sharing.ts'
import { seedThreeVersions } from '../../support/utils/versions.ts'

test('the versions of a team folder file are listed for a share recipient', async ({ page, user, teamFolder, createUser, openSession }) => {
	const fileName = 'versioned.txt'
	await seedThreeVersions(page.request, user, `${teamFolder.mountPoint}/${fileName}`)

	const recipient = await createUser()
	const recipientPage = await openSession(recipient)
	await createUserShare(page.request, `/${teamFolder.mountPoint}/${fileName}`, recipient)
	await waitForShare(recipientPage.request, recipient, '', fileName)

	const filesList = new FilesListPage(recipientPage)
	const versionsTab = new VersionsTab(recipientPage)
	await filesList.open()
	await versionsTab.openFor(filesList, fileName)

	await expect(versionsTab.versions()).toHaveCount(3)
	await expect(versionsTab.version(0)).toContainText('Current version')
	await expect(versionsTab.version(2)).toContainText('Initial version')
})
