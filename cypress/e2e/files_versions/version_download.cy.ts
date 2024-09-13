/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { assertVersionContent, setupFilesVersions } from './filesVersionsUtils'

describe('Versions download', () => {
	beforeEach(() => {
		setupFilesVersions()
	})

	it('Download versions and assert their content', () => {
		assertVersionContent(0, 'v3')
		assertVersionContent(1, 'v2')
		assertVersionContent(2, 'v1')
	})
})
