/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { configureNextcloud, runExec, runOcc, startNextcloud, stopNextcloud, waitOnNextcloud } from '@nextcloud/e2e-test-server/docker'
import { execSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

/**
 * The server branch matching the app: `stable<max-version>` if it exists, `master` otherwise.
 */
function getServerBranch() {
	if (process.env.BRANCH) {
		return process.env.BRANCH
	}

	const appinfo = readFileSync('appinfo/info.xml').toString()
	const maxVersion = appinfo.match(/<nextcloud min-version="\d+" max-version="(\d+)" \/>/)?.[1]
	if (!maxVersion) {
		return 'master'
	}

	const branch = `stable${maxVersion}`
	const refs = execSync(`git ls-remote --heads https://github.com/nextcloud/server.git refs/heads/${branch}`).toString('utf-8')
	return refs.includes(`refs/heads/${branch}`) ? branch : 'master'
}

async function start() {
	const ip = await startNextcloud(getServerBranch(), true, {
		exposePort: Number.parseInt(process.env.NEXTCLOUD_PORT ?? '8090', 10),
	})
	await waitOnNextcloud(ip)
	await configureNextcloud(['groupfolders'])

	process.stdout.write('\nApply custom configuration for Playwright tests\n')
	// The tests seed their data through occ from several workers at once
	await runExec(['php', '-r', '$db = new SQLite3("data/owncloud.db");$db->busyTimeout(5000);$db->exec("PRAGMA journal_mode = wal;");'])
	process.stdout.write('├─ Enabled SQLite WAL mode\n')

	// Otherwise app config changed through occ only reaches the web server once its cache expired
	await runOcc(['config:system:set', 'cache_app_config', '--value', 'false', '--type', 'boolean'])
	process.stdout.write('├─ Disabled caching AppConfig\n')

	// Team folders are created through occ, which cannot invalidate the web server's
	// cache of the users' mounts, so a user would not see a new folder until it expired
	await runOcc(['config:system:set', 'fs_mount_cache_duration', '--value', '0', '--type', 'integer'])
	process.stdout.write('├─ Disabled caching user mounts\n')

	// Tests that assert on folder contents should not have to know about the skeleton files
	await runOcc(['config:system:set', 'skeletondirectory', '--value', ''])
	await runOcc(['config:system:set', 'templatedirectory', '--value', ''])
	process.stdout.write('├─ Disabled skeleton and template files for new users\n')

	process.stdout.write('└─ Nextcloud container ready to run Playwright tests\n')
}

async function stop() {
	process.stderr.write('Stopping Nextcloud server…\n')
	await stopNextcloud({ saveLogTo: 'nextcloud.log' })
	process.exit(0)
}

process.on('SIGTERM', stop)
process.on('SIGINT', stop)

await start()

// Idle to wait for shutdown
while (true) {
	await new Promise((resolve) => setTimeout(resolve, 5000))
}
