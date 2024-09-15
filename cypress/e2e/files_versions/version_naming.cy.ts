/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { nameVersion, setupFilesVersions } from './filesVersionsUtils'

describe('Versions naming', () => {
	beforeEach(() => {
		setupFilesVersions()
	})

	it('Names the versions', () => {
		nameVersion(2, 'v1')
		cy.get('#tab-version_vue').within(() => {
			cy.get('[data-files-versions-version]').eq(2).contains('v1')
			cy.get('[data-files-versions-version]').eq(2).contains('Initial version').should('not.exist')
		})

		nameVersion(1, 'v2')
		cy.get('#tab-version_vue').within(() => {
			cy.get('[data-files-versions-version]').eq(1).contains('v2')
		})

		nameVersion(0, 'v3')
		cy.get('#tab-version_vue').within(() => {
			cy.get('[data-files-versions-version]').eq(0).contains('v3 (Current version)')
		})
	})
})
