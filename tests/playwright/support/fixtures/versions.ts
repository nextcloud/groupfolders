/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { seedThreeVersions } from '../utils/versions.ts'
import { test as base } from './groupfolders.ts'

interface VersionsFixtures {
	/**
	 * Name of a file with three versions in `teamFolder`. Every test starts
	 * with the files app showing the team folder and the Versions tab of the
	 * file opened.
	 */
	versionedFile: string
}

export const test = base.extend<VersionsFixtures>({
	versionedFile: [async ({ page, user, teamFolder, filesList, versionsTab }, use) => {
		const name = 'versioned.txt'
		await seedThreeVersions(page.request, user, `${teamFolder.mountPoint}/${name}`)

		await filesList.openFolder(`/${teamFolder.mountPoint}`)
		await versionsTab.openFor(filesList, name)
		await use(name)
	}, { auto: true }],
})

export { expect } from './groupfolders.ts'
