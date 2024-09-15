/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { deleteVersion, setupFilesVersions } from './filesVersionsUtils'

describe('Versions restoration', () => {
	beforeEach(() => {
		setupFilesVersions()
	})

	it('Delete initial version', () => {
		cy.get('[data-files-versions-version]').should('have.length', 3)
		deleteVersion(2)
		cy.get('[data-files-versions-version]').should('have.length', 2)
	})
})
