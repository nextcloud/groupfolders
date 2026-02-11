/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { SetupInfo } from './filesVersionsUtils.ts'

import { openVersionsPanel, setupFilesVersions } from './filesVersionsUtils.ts'
import { createShare } from '../FilesSharingUtils.ts'
import { navigateToFolder } from '../files/filesUtils.ts'

describe('Versions creation', () => {
	let setupInfo: SetupInfo

	beforeEach(() => {
		setupFilesVersions()
			.then((_setupInfo) => {
				setupInfo = _setupInfo

				cy.createRandomUser().then((bob) => {
					cy.login(setupInfo.user)
					cy.visit('/apps/files')
					navigateToFolder(setupInfo.groupFolderName)
					createShare(setupInfo.fileName, bob.userId)

					cy.login(bob)
					cy.visit('/apps/files')
					openVersionsPanel(setupInfo.filePath)
				})
			})
	})

	it('List versions as expected', () => {
		cy.get('#tab-files_versions').within(() => {
			cy.get('[data-files-versions-version]').should('have.length', 3)
			cy.get('[data-files-versions-version]').eq(0).contains('Current version')
			cy.get('[data-files-versions-version]').eq(2).contains('Initial version')
		})
	})
})
