/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import react from '@vitejs/plugin-react'
import { join } from 'node:path'

export default createAppConfig({
	initFiles: join(__dirname, 'src/init-files.ts'),
	settings: join(__dirname, 'src/settings/index.tsx'),
}, {
	createEmptyCSSEntryPoints: true,
	emptyOutputDirectory: {
		additionalDirectories: ['css'],
	},
	config: {
		plugins: [react()],
	},
})
