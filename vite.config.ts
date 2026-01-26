/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import react from '@vitejs/plugin-react'
import { join } from 'node:path'

export default createAppConfig({
	init: join(__dirname, 'src/init.ts'),
	files: join(__dirname, 'src/files.js'),
	settings: join(__dirname, 'src/settings/index.tsx'),
}, {
	createEmptyCSSEntryPoints: true,
	config: {
		plugins: [react()],
	},
})
