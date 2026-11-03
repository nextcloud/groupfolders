/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/// <reference types="@types/node" />

import { defineConfig, devices } from '@playwright/test'

type DeviceDescriptor = typeof devices[string]
const BROWSER_CONFIG_CHROME: DeviceDescriptor & { channel: string } = {
	...devices['Desktop Chrome'],
	channel: process.env.CI
		? 'chrome' // on CI use the chrome browser provided by the GitHub Actions runner
		: 'chromium', // locally use the default playwright chromium browser
}

// Port of the Nextcloud container under test
const NEXTCLOUD_PORT = process.env.NEXTCLOUD_PORT ?? '8090'

// Specs that change instance-wide configuration (server side encryption,
// version retention) and therefore must not run alongside other tests.
const INSTANCE_CONFIG_SPECS = /\/e2e\/instance-config\//

export default defineConfig({
	testDir: './tests/playwright/e2e',
	fullyParallel: true,
	// most tests seed several users, a team folder and its contents through occ
	// before they even open the files app
	timeout: 60_000,
	expect: {
		timeout: 10_000,
	},
	// ensure no `test.only` is left in the code causing false positives
	forbidOnly: !!process.env.CI,
	// on CI we retry once to get traces of failures
	retries: process.env.CI ? 1 : 0,
	// The SQLite database of the test container fails concurrent writes with
	// "database is locked", so tests run one at a time; CI shards them instead
	workers: 1,
	reporter: process.env.CI ? [['blob'], ['dot'], ['github']] : 'html',
	use: {
		baseURL: `http://localhost:${NEXTCLOUD_PORT}/index.php/`,
		trace: 'on-first-retry',
	},

	projects: [
		{
			name: 'default',
			testIgnore: INSTANCE_CONFIG_SPECS,
			use: {
				...BROWSER_CONFIG_CHROME,
			},
		},
		{
			name: 'instance-config',
			testMatch: INSTANCE_CONFIG_SPECS,
			fullyParallel: false,
			use: {
				...BROWSER_CONFIG_CHROME,
			},
		},
	],

	webServer: {
		command: 'node tests/playwright/start-nextcloud-server.js',
		env: {
			NEXTCLOUD_PORT,
		},
		stderr: 'pipe',
		stdout: 'pipe',
		// we use sigterm to notify the script to stop the container
		// if it does not respond, we force kill it after 10 seconds
		gracefulShutdown: {
			signal: 'SIGTERM',
			timeout: 10_000,
		},
		reuseExistingServer: !process.env.CI,
		timeout: 5 * 60 * 1000,
		wait: {
			stdout: /Nextcloud container ready to run Playwright tests/,
		},
	},
})
