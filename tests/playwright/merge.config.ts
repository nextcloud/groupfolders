/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Needed to merge the blob reports of the CI shards
export default {
	testDir: 'tests/playwright/e2e',
	reporter: [['html', { open: 'never' }]],
}
