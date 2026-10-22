/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { join } from 'node:path'

export default createAppConfig({
	initFiles: join(__dirname, 'src/init-files.ts'),
	settings: join(__dirname, 'src/admin-settings.ts'),
	'settings-security': join(__dirname, 'src/admin-settings-security.ts'),
}, {
	createEmptyCSSEntryPoints: true,
	emptyOutputDirectory: {
		additionalDirectories: ['css'],
	},
})
