/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { assertVersionContent, doesNotHaveAction, restoreVersion, setupFilesVersions } from './filesVersionsUtils'

describe('Versions restoration', () => {
	before(() => {
		setupFilesVersions()
	})

	it('Current version does not have restore action', () => {
		doesNotHaveAction(0, 'restore')
	})

	it('Restores initial version', () => {
		restoreVersion(2)

		cy.get('#tab-version_vue').within(() => {
			cy.get('[data-files-versions-version]').should('have.length', 3)
			cy.get('[data-files-versions-version]').eq(0).contains('Current version')
			cy.get('[data-files-versions-version]').eq(2).contains('Initial version').should('not.exist')
		})
	})

	it('Downloads versions and assert their content', () => {
		assertVersionContent(0, 'v1')
		assertVersionContent(1, 'v3')
		assertVersionContent(2, 'v2')
	})
})
